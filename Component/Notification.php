<?php

namespace Northrook\Latte\Component;

use Latte\Runtime\HtmlStringable;
use Northrook\Core\Timestamp;
use Northrook\Core\Trait\PropertyAccessor;
use Northrook\Latte\Compiler\Component;
use function Northrook\hashKey;
use function Northrook\normalizeKey;
use function Northrook\normalizePath;

/**
 *
 * @property-read string    $type          One of 'info', 'success', 'warning', 'danger', or 'notice'
 * @property-read string    $icon          Built-in SVG icons for each type
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
class Notification extends Component
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
     * @param string       $type  = [ 'info', 'success', 'warning', 'danger', 'notice' ][$any]
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
        parent::__construct( 'notification' );

        $this->componentAttributes->class->set( 'notification', $type );

        $this->parameters[ 'type' ]        = normalizeKey( $type );
        $this->parameters[ 'message' ]     = \trim( $message );
        $this->parameters[ 'description' ] = $description ? trim( $description ) : null;
        $this->parameters[ 'timeout' ]     = $timeout;
        $this->instances[]                 = new Timestamp();

    }

    public function __get( string $property ) : null | string | int | array | HtmlStringable {
        $get = parent::__get( $property );
        return $get ?? match ( $property ) {
            'key'           => hashKey( $this->parameters ),
            'type'          => $this->parameters[ 'type' ],
            'icon'          => $this->html( $this->fallbackIcon() ),
            'message'       => $this->parameters[ 'message' ],
            'description'   => $this->parameters[ 'description' ],
            'timeout'       => $this->parameters[ 'timeout' ],
            'instances'     => $this->instances,
            'timestamp'     => $this->getTimestamp(),
            'unixTimestamp' => $this->getTimestamp()->unixTimestamp,
            'when'          => $this->html( $this->timestampWhen() ),
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
    final public function bump() : self {
        $this->instances[] = new Timestamp();
        return $this;
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
    public function timeout( ?int $milliseconds ) : self {
        $this->parameters[ 'timeout' ] = $milliseconds;
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


    private function timestampWhen() : string {
        $now       = time();
        $unix      = $this->getTimestamp()->unixTimestamp;
        $timestamp = $this->getTimestamp()->format( Timestamp::FORMAT_HUMAN, true );

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


    private function fallbackIcon() : ?string {
        $icons = [
            'success' => '<svg class="icon" fill="currentColor" viewbox="0 0 16 16"><path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/></svg>',
            'info'    => '<svg class="icon" fill="currentColor" viewbox="0 0 16 16"><path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/></svg>',
            'danger'  => '<svg class="icon" fill="currentColor" viewbox="0 0 16 16"><path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zM5.354 4.646a.5.5 0 1 0-.708.708L7.293 8l-2.647 2.646a.5.5 0 0 0 .708.708L8 8.707l2.646 2.647a.5.5 0 0 0 .708-.708L8.707 8l2.647-2.646a.5.5 0 0 0-.708-.708L8 7.293 5.354 4.646z"/></svg>',
            'warning' => '<svg class="icon" fill="none" viewbox="0 0 16 16"><path fill="currentColor" fill-rule="evenodd" clip-rule="evenodd" d="M9.336.757c-.594-1.01-2.078-1.01-2.672 0L.21 11.73C-.385 12.739.357 14 1.545 14h12.91c1.188 0 1.93-1.261 1.336-2.27L9.336.757ZM9 4.5C9 4 9 4 8 4s-1 0-1 .5l.383 3.538c.103.505.103.505.617.505s.514 0 .617-.505L9 4.5Zm-1 7.482c1.028 0 1.028 0 1.028-1.01 0-1.009 0-1.009-1.028-1.009s-1.028.094-1.028 1.01c0 1.008 0 1.008 1.028 1.008Z"/></svg>',
            'notice'  => '<svg class="icon" fill="none" viewbox="0 0 16 16"><path fill="currentColor" fill-rule="evenodd" clip-rule="evenodd" d="M6.983 1.006a.776.776 0 0 1 .667.634l1.781 9.967 1.754-3.925a.774.774 0 0 1 .706-.46h3.335c.427 0 .774.348.774.778 0 .43-.347.778-.774.778h-2.834L9.818 14.54a.774.774 0 0 1-1.468-.181L6.569 4.393 4.816 8.318a.774.774 0 0 1-.707.46H.774A.776.776 0 0 1 0 8c0-.43.347-.778.774-.778h2.834L6.182 1.46a.774.774 0 0 1 .8-.453Z"/></svg>',
        ];
        return $icons[ $this->type ] ?? $icons[ 'notice' ];
    }

    static public function getAssets() : array {
        return [
            __DIR__ . '/Notification/notification.css',
            __DIR__ . '/Notification/notification.js',
        ];
    }

    protected function templatePath() : string {
        return normalizePath( __DIR__ . '/Notification/notification.latte' );
    }
}