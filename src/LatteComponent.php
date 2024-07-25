<?php

namespace Northrook\Latte\src;

use Northrook\Core\Interface\Printable;
use Northrook\Core\Trait\PrintableClass;
use Northrook\Latte\Render;
use function Northrook\classBasename;
use function Northrook\normalizePath;

abstract class LatteComponent implements Printable
{
    use PrintableClass;

    private readonly string $templatePath;
    private readonly string $templateType;

    public function __toString() : string {
        return Render::toString( $this->templatePath(), [ $this->templateType => $this ] );
    }

    final protected function templateType( ?string $string = null ) : string {
        return $this->templateType ??= strtolower( $string ?? classBasename( $this::class ) );
    }

    final protected function templatePath( ?string $path = null ) : string {
        return $this->templatePath ??= normalizePath(
            $path ?? dirname( __DIR__ ) . "/templates/components/{$this->templateType()}.latte",
        );
    }

}