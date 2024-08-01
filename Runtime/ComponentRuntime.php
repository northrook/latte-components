<?php

namespace Northrook\Latte\Runtime;

use Latte\Runtime\HtmlStringable;
use Northrook\Latte\Component\Notification;

/**
 * @internal
 */
final class ComponentRuntime
{
    public function toast( string $type, string $title ) : HtmlStringable {
        return new Notification( $type, $title );
    }
}