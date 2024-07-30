<?php

namespace Northrook\Latte\Compiler;

final class ComponentParser extends TemplateParser
{

    protected function parseTemplateContent() : void {
        dump( $this->content );
    }
}