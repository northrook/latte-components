<?php

declare( strict_types = 1 );

namespace Northrook\Latte\Compiler;

use Latte\Runtime\Html;
use Latte\Runtime\HtmlStringable;
use Northrook\Core\Interface\Printable;
use Northrook\Core\Trait\PrintableClass;
use Northrook\HTML\Element\Attributes;
use Northrook\Latte;
use Northrook\Latte\Runtime\ComponentAssetHandler;
use function Northrook\hashKey;
use function Northrook\normalizeKey;

/**
 * @property-read array  $attributes     // Retrieve all parent attributes as a {name:value} array
 * @property-read string $componentID    // An ID unique to this object
 *
 * @author Martin Nielsen <mn@northrook.com>
 */
abstract class Component implements Printable, HtmlStringable
{
    use PrintableClass;

    private readonly string $templateComponentID;

    protected readonly Attributes $componentAttributes;
    protected readonly string     $templateType;
    protected readonly string     $templatePath;

    public function __construct(
        string $type,
        array  $attributes = [],
    ) {
        $this->componentAttributes = new Attributes( $attributes );
        $this->templateType        = normalizeKey( $type );
    }

    public function __get( string $property ) {
        return match ( $property ) {
            'attributes'  => $this->componentAttributes->getAttributes( true ),
            'componentID' => $this->templateComponentID,
            default       => null,
        };
    }

    final public function __toString() : string {
        $this->templateComponentID = hashKey( [ $this, \spl_object_id( $this ) ] );
        ComponentAssetHandler::registerComponent( $this->templateType, $this::class );

        return Latte::render( $this->templatePath(), [ $this->templateType => $this ], postProcessing : false );
    }

    final public function attr() : ?HtmlStringable {
        return $this->html( \implode( ' ', $this->componentAttributes->getAttributes() ) );
    }

    /**
     * Returns an array of all CSS and JS assets.
     *
     * @return string[]
     */
    abstract static public function getAssets() : array;

    abstract protected function templatePath() : string;

    final protected function html( null | string | \Stringable $value ) : ?HtmlStringable {
        return $value ? new Html( (string) $value ) : null;
    }
}