<?php

namespace Northrook\Latte\src\Component;

use InvalidArgumentException;
use Northrook\Core\Timestamp;
use Northrook\Core\Trait\PropertyAccessor;
use Northrook\HTML\Element\Attributes;
use Northrook\HTML\Element\Content;
use Northrook\Latte\src\LatteComponent;
use Northrook\Logger\Log;
use function Northrook\hashKey;
use function trim;

/**
 *
 * @property-read string    $type          One of 'info', 'success', 'warning', 'error', or 'notice'
 * @property-read string    $message       The main message to show the user
 * @property-read ?string   $description   [optional] Provide more details.
 * @property-read ?int      $timeout       How long before the message should time out, in milliseconds
 * @property-read Timestamp $timestamp     The most recent timestamp object
 *
 * @property-read string    $key           Unique key to identify this object internally
 * @property-read array     $instances     // All the times this exact Notification has been created since it was last rendered
 * @property-read int       $unixTimestamp // The most recent timestamps' unix int
 * @property-read ?string   $when
 *
 * @author Martin Nielsen <mn@northrook.com>
 */
class Notification extends LatteComponent
{
    use PropertyAccessor;
    
    private array $instances  = [];
    private array $parameters = [
        'type'        => null,
        'message'     => null,
        'description' => null,
        'timeout'     => null,
    ];


    /**
     * @param string       $type  = [ 'info', 'success', 'warning', 'error', 'notice' ][$any]
     * @param string       $message
     * @param null|string  $description
     * @param null|int     $timeout
     */
    public function __construct(
        string  $type,
        string  $message,
        ?string $description = null,
        ?int    $timeout = null,
    ) {

        $content = new Content ( $message );

        $this->setType( $type );
        $this->parameters[ 'message' ]     = trim( $message );
        $this->parameters[ 'description' ] = $description ? trim( $description ) : null;
        $this->parameters[ 'timeout' ]     = $timeout;
        $this->instances[]                 = new Timestamp();
    }

    public function __get( string $property ) : null | string | int | array {
        return match ( $property ) {
            'key'           => hashKey( $this->parameters ),
            'type'          => $this->parameters[ 'type' ],
            'message'       => $this->parameters[ 'message' ],
            'description'   => $this->parameters[ 'description' ],
            'timeout'       => $this->parameters[ 'timeout' ],
            'instances'     => $this->instances,
            'timestamp'     => $this->getTimestamp(),
            'unixTimestamp' => $this->getTimestamp()->unixTimestamp,
            'when'          => $this->timestampWhen(),
        };
    }

    /**
     * Format the most recent timestamp object
     *
     * The {@see \Northrook\Core\Timestamp} object provides commonly used formats as constants.
     *
     * @link https://www.php.net/manual/en/datetime.format.php#refsect1-datetime.format-parameters Formatting Documentation
     *
     * @param string  $format
     *
     * @return string
     */
    public function timestamp( string $format = Timestamp::FORMAT_HUMAN ) : string {
        return $this->getTimestamp()->format( $format );
    }

    /**
     * Indicate that this notification has been seen before.
     *
     * - Adds a timestamp to the {@see Notification::$instances} array.
     *
     * @return $this
     */
    public function bump() : Notification {
        $this->instances[] = new Timestamp();
        return $this;
    }

    /**
     * Set the {@see Notification::$type} to `notice`
     *
     * @return Notification
     */
    public function notice() : Notification {
        return $this->setType( 'notice' );
    }

    /**
     * Set the {@see Notification::$type} to `info`
     *
     * @return Notification
     */
    public function info() : Notification {
        return $this->setType( 'info' );
    }

    /**
     * Set the {@see Notification::$type} to `success`
     *
     * @return Notification
     */
    public function success() : Notification {
        return $this->setType( 'success' );
    }

    /**
     * Set the {@see Notification::$type} to `warning`
     *
     * @return Notification
     */
    public function warning() : Notification {
        return $this->setType( 'warning' );
    }

    /**
     * Set the {@see Notification::$type} to `error`
     *
     * @return Notification
     */
    public function error() : Notification {
        return $this->setType( 'error' );
    }

    /**
     * Set the {@see Notification::$timeout} value.
     *
     * Recommended range: `3500 - 8000`
     *
     * - `0` requires the user manually dismiss the {@see Notification}.
     * - `null` will fall back to the system default, if available.
     * - If a value is set, it is very likely that the front-end will enforce a minimum duration.
     *
     * @param ?int  $milliseconds
     *
     * @return $this
     */
    public function timeout( ?int $milliseconds ) : Notification {
        $this->timeout = $milliseconds;
        return $this;
    }

    /**
     * How many times has this been triggered since the last render?
     *
     * @return int
     */
    public function count() : int {
        return count( $this->instances );
    }

    /**
     * Retrieve the {@see Timestamp} object.
     *
     * @return Timestamp
     * @internal
     */
    private function getTimestamp() : Timestamp {
        return $this->instances[ array_key_last( $this->instances ) ];
    }

    /**
     * Set the {@see Notification::$type} value.
     *
     * If the provided value is invalid, an exception will be logged and the value set to `notice`.
     *
     * @param string  $type
     *
     * @return Notification
     * @internal
     */
    private function setType( string $type ) : Notification {
        try {
            $this->parameters[ 'type' ] = in_array( $type, [ 'info', 'success', 'warning', 'error', 'notice' ] )
                ? $type
                : throw new InvalidArgumentException( "Invalid type '{$type}' used for " . Notification::class );
        }
        catch ( InvalidArgumentException $exception ) {
            Log::exception( $exception );
            $this->parameters[ 'type' ] = 'notice';
        }

        return $this;
    }

    private function timestampWhen() : string {
        $now       = time();
        $unix      = $this->getTimestamp()->unixTimestamp;
        $timestamp = $this->getTimestamp()->format( Timestamp::FORMAT_HUMAN, true );;

        // If this occurred less than 5 seconds ago, count it as now
        if ( ( $now - $unix ) < 5 ) {
            return '<span class="datetime-when">Now</span><span class="datetime-timestamp">' . $timestamp . '</span>';
        }
        // If this occurred less than 12 hours ago, it is 'today'
        if ( ( $now - $unix ) < 43200 ) {
            return '<span class="datetime-when">Today</span><span class="datetime-timestamp">' . $timestamp . '</span>';
        }
        // Otherwise print the whole day
        return $timestamp;
    }

}