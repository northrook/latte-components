<?php

namespace Northrook\Latte\src\Element;

use Latte\Runtime\HtmlStringable;
use Northrook\HTML\Element;

final class Button extends Element implements HtmlStringable
{
    public function __construct( array $attributes = [], mixed $content = null ) {
        parent::__construct( 'button', $attributes, $content );
    }

    public static function close(
        string $label = 'Close',
    ) : Button {
        return new Button( [ 'class' => 'icon close', 'aria-label' => $label ] );
    }
}