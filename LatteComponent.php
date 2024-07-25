<?php

declare( strict_types = 1 );

namespace Northrook\Latte;

use Latte\Runtime\Html;
use Latte\Runtime\HtmlStringable;
use Northrook\Core\Interface\Printable;
use Northrook\Core\Trait\PrintableClass;
use Northrook\HTML\Element\Attributes;
use Northrook\Resource\Path;
use function Northrook\hashKey;
use function Northrook\normalizeKey;
use function Northrook\normalizePath;

/**
 * @property-read array  $attributes     // Retrieve all parent attributes as a {name:value} array
 * @property-read string $componentID    // An ID unique to this object
 *
 * @author Martin Nielsen <mn@northrook.com>
 */
abstract class LatteComponent implements Printable
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

    final public function returnHtml( null | string | \Stringable $value ) : ?HtmlStringable {
        return $value ? new Html( (string) $value ) : null;
    }

    final public function __toString() : string {
        $this->templateComponentID = hashKey( [ $this, \spl_object_id( $this ) ] );
        return Render::toString( $this->templatePath(), [ $this->templateType => $this ] );
    }

    final protected function templatePath( ?string $path = null ) : string {
        return $this->templatePath ??= normalizePath(
            $path ?? __DIR__ . "/templates/components/{$this->templateType}.latte",
        );
    }
}