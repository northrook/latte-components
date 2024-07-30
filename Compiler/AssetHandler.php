<?php

declare( strict_types = 1 );

namespace Northrook\Latte\Compiler;

use Northrook\Asset\Script;
use Northrook\Asset\Stylesheet;
use Northrook\AssetGenerator\Asset;
use Northrook\Core\Trait\SingletonClass;
use Northrook\Resource\Path;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use function Northrook\arrayUnique;

final class AssetHandler
{

    use SingletonClass;

    private array $calledComponents = [];
    private array $assetCache       = [];
    private array $assetDirectories = [];
    private bool  $frozen           = false;

    public function __construct(
        string | array $assetDirectories,
        public bool    $inlineAssets = true,
    ) {
        // Assign each provided path
        \array_map( [ $this, 'addDirectory' ], (array) $assetDirectories );

        $this::$instance = $this;
    }

    public function getComponentAssets( string $component ) : array {

        // Return already compiled Assets if they exist
        if ( isset( $this->assetCache[ $component ] ) ) {
            return $this->assetCache[ $component ];
        }

        // Hold assets for this $component
        $componentAssets = [];

        // Loop through each asset directory for this $component  in the chain
        foreach ( $this->getDirectories() as $directoryPath ) {
            // Accept the first match
            if ( $componentAssets = \glob( "$directoryPath->path/$component*" ) ) {
                break;
            }
        }

        foreach ( $componentAssets as $assetPath ) {
            $assetFile = new Path( $assetPath );

            if ( !$assetFile->isReadable ) {
                throw new FileException( 'File "' . $assetPath . '" is not readable' );
            }

            $asset = match ( $assetFile->extension ) {
                'css'   => new Stylesheet( $assetFile->path, inline : $this->inlineAssets, prefix : 'component' ),
                'js'    => new Script( $assetFile->path, inline : $this->inlineAssets, prefix : 'component' ),
                default => throw new \UnexpectedValueException( 'Unexpected file extension: ' . $assetFile->extension ),
            };

            $this->assetCache[ $component ][ $asset->assetID ] = $asset;
        }


        return $this->assetCache[ $component ];
    }

    public function getEnqueuedAssets() : array {
        $assets = [];

        foreach ( arrayUnique( $this->calledComponents ) as $componentID => $component ) {
            $assets += $this->getComponentAssets( $component );
        }

        return $assets;
    }

    public function addDirectory( string | Path $path ) : self {

        if ( $this->frozen ) {
            throw new \LogicException(
                "The 'getDirectories' method has been called, freezing the property. No further directories can be added at this time. ",
            );
        }

        $directoryPath = $path instanceof Path
            ? $path
            : new Path( $path );

        $this->assetDirectories[ $directoryPath->path ] = $directoryPath;

        return $this;
    }

    public static function registerComponent( string $componentID, string $templateType ) : void {
        self::getInstance( true, [] )->calledComponents[ $componentID ] = $templateType;
    }

    public static function get( string $component ) : array {
        return AssetHandler::getInstance( true, [] )
                           ->getComponentAssets( $component );
    }

    public static function getAssets() : array {
        return AssetHandler::getInstance( true, [] )
                           ->getEnqueuedAssets();
    }

    public static function getCoreAssets( bool $inline = true ) : array {

        $assets = [];

        // Accept the first match
        $coreAssets = \glob( \dirname( __DIR__, 2 ) . '/assets/styles/*' );

        foreach ( $coreAssets as $assetPath ) {
            $assetFile = new Path( $assetPath );

            if ( !$assetFile->isReadable ) {
                throw new FileException( 'File "' . $assetPath . '" is not readable' );
            }

            $asset = match ( $assetFile->extension ) {
                'css'   => new Stylesheet( $assetFile->path, inline : $inline, prefix : 'component' ),
                'js'    => new Script( $assetFile->path, inline : $inline, prefix : 'component' ),
                default => throw new \UnexpectedValueException( 'Unexpected file extension: ' . $assetFile->extension ),
            };

            $assets[ $asset->assetID ] = $asset;
        }

        return $assets;
    }

    public static function getDirectories() : array {

        $componentAssets = self::getInstance( true, [] );

        if ( $componentAssets->frozen ) {
            return $componentAssets->assetDirectories;
        }


        // To ensure default component assets are checked last,
        $nativeAssets = new Path( \dirname( __DIR__, 2 ) . '/assets/components' );

        // Check if the user manually assigned the directory anywhere in the chain
        if ( \array_key_exists( $nativeAssets->path, $componentAssets->assetDirectories ) ) {
            // If so, unset it and move it to the end of the array
            unset( $componentAssets->assetDirectories[ $nativeAssets->path ] );
            $componentAssets->assetDirectories[ $nativeAssets->path ] = $nativeAssets;
        }

        $componentAssets->frozen = true;

        return $componentAssets->assetDirectories;
    }
}