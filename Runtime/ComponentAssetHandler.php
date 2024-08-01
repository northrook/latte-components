<?php

namespace Northrook\Latte\Runtime;

use Northrook\Asset\Script;
use Northrook\Asset\Stylesheet;
use Northrook\Core\Trait\SingletonClass;
use Northrook\Resource\Path;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use function Northrook\normalizeKey;
use function Northrook\normalizePath;

final class ComponentAssetHandler
{
    use SingletonClass;

    public const PREFIX = 'component';

    private bool  $frozen      = false; // Whether more directories can be added or not
    private array $directories = [];    // Alternative locations for Component Assets

    private array $ignoredComponents = [];
    private array $calledComponents  = [];
    private array $runtimeCache      = [];
    private array $assets            = [];

    public function __construct(
        string | array $assetDirectories,
        public bool    $inlineAssets = true,
    ) {
        // Assign each provided path
        \array_map( [ $this, 'addDirectory' ], (array) $assetDirectories );

        $this::$instance = $this;
    }


    /**
     * @param string  $html
     *
     * @return string
     */
    public function handleDocumentInjection( string $html ) : string {
        if ( !$this->getAssets() ) {
            return $html;
        }

        // If we have a head, we know we need to check for existing assets
        if ( \str_contains( $html, '</head>' ) ) {
            [ $head, $body ] = \explode( '</head>', $html );

            foreach ( $this->assets as $asset ) {
                $head .= "\t$asset\n";
            }

            $head .= '</head>';
            return $head . $body;
        }

        $assets = '';
        foreach ( $this->assets as $asset ) {
            $assets .= "\t$asset\n";
        }

        return $assets . $html;
    }


    public function getAssets() : array {

        foreach ( $this->calledComponents as $componentType => $className ) {
            if ( \in_array( $componentType, $this->ignoredComponents, true ) ) {
                continue;
            }
            $this->assets += $this->getComponentAssets( $className, $componentType );
        }

        return $this->assets;
    }

    /**
     * @param class-string  $className
     * @param ?string       $componentType
     *
     * @return array
     */
    public function getComponentAssets( string $className, ?string $componentType = null ) : array {

        $this->frozen = true;

        // Return already compiled Assets if they exist
        if ( isset( $this->runtimeCache[ $className ] ) ) {
            return $this->runtimeCache[ $className ];
        }

        // Derive componentType from className if needed
        $componentType ??= \strtolower( \substr( $className, \strripos( $className, '\\' ) + 1 ) );

        // Hold assets for this $component
        $componentAssets = null;

        // Loop through each asset directory for this $component  in the chain
        foreach ( $this->directories as $directoryPath ) {
            if ( $componentAssets = \glob(
                normalizePath( "$directoryPath/$componentType" ) . '*',
            ) ) {
                break;
            }
        }

        if ( !$componentAssets && method_exists( $className, 'getAssets' ) ) {
            $componentAssets = $className::getAssets();
        }

        foreach ( $componentAssets as $assetPath ) {
            $assetFile = new Path( $assetPath );

            if ( !$assetFile->isReadable ) {
                throw new FileException( 'File "' . $assetPath . '" is not readable' );
            }

            $asset = match ( $assetFile->extension ) {
                'css'   => new Stylesheet( $assetFile->path, inline : $this->inlineAssets, prefix : $this::PREFIX ),
                'js'    => new Script( $assetFile->path, inline : $this->inlineAssets, prefix : $this::PREFIX ),
                default => throw new \UnexpectedValueException( 'Unexpected file extension: ' . $assetFile->extension ),
            };

            $this->runtimeCache[ $className ][ $asset->assetID ] = $asset;
        }


        return $this->runtimeCache[ $className ];
    }

    /**
     * @param string  $templateType
     * @param string  $className
     *
     * @return void
     */
    public static function registerComponent( string $templateType, string $className ) : void {
        // Fetch the current instance if it exists
        ComponentAssetHandler::getInstance( true, [] )
            // Assign each called component only once
            ->calledComponents[ $templateType ] ??= $className;
    }

    public function ignore( string $component ) : self {

        if ( isset( $this->ignoredComponents[ $component ] ) ) {
            return $this;
        }

        $component = \substr( $component, \strlen( self::PREFIX ) + 1 );
        $component = \substr( $component, 0, \strripos( $component, '-' ) );

        $this->ignoredComponents[ $component ] = normalizePath( $component );

        return $this;
    }


    /**
     * @param string  $path
     *
     * @return $this
     */
    public function addDirectory( string $path ) : self {

        if ( $this->frozen ) {
            throw new \LogicException(
                "The 'getDirectories' method has been called, freezing the property. No further directories can be added at this time. ",
            );
        }

        $this->directories[] = $path;

        return $this;
    }
}