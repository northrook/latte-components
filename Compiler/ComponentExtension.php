<?php

namespace Northrook\Latte\Compiler;


use Latte;
use Latte\Compiler\Node;
use Latte\Compiler\Nodes\Html\AttributeNode;
use Latte\Compiler\Nodes\Html\ElementNode;
use Latte\Compiler\Nodes\Php\ExpressionNode;
use Latte\Compiler\NodeTraverser;
use Northrook\Latte\Runtime\ComponentRuntime;

final class ComponentExtension extends CompilerPassExtension
{
    use NodeCompilerTrait;

    public readonly ComponentRuntime $componentRuntime;


    // Option to add classes extending Component
    // Called through Runtime__call() using $this->global

    public function __construct() {
        $this->componentRuntime = new ComponentRuntime();
    }

    public function traverseNodes() : array {
        return [
            [ $this, 'fieldInput' ],
        ];
    }

    public function fieldInput( Node $node ) : mixed {

        if ( $node instanceof ExpressionNode ) {
            return NodeTraverser::DontTraverseChildren;
        }

        if ( $node instanceof ElementNode && $node->is( 'field:input' ) ) {

            foreach ( $node->attributes->children as $index => $attribute ) {
                if ( !$attribute instanceof AttributeNode ) {
                    unset( $node->attributes->children[ $index ] );
                }
            }
            // dump( $node->attributes );

            $component = function ( Latte\Compiler\PrintContext $printContext, AttributeNode...$args ) {
                $arguments = [];
                foreach ( $args as $arg ) {
                    // dump( $this->nodeRawValue( $arg->value->print( $printContext ) ) );
                    // $value = trim( strstr( $arg->value->print( $printContext ), ' ' ), " \n\r\t\v\0;" );
                    // dump( $value );
                    $arguments[] = $this->nodeRawValue( $arg->value->print( $printContext ) );
                };
                return 'echo $this->global->component->toast( ' . implode( ', ', $arguments ) . ' );';

            };

            $node = new Latte\Compiler\Nodes\AuxiliaryNode(
                $component, [ ... $node->attributes->children ],
            );
        }

        return $node;
    }

    private function nodeRawValue( string $string ) : string {
        if ( \str_starts_with( $string, 'echo ' ) ) {
            $string = \substr( $string, \strlen( 'echo ' ) );
        }


        if ( \str_starts_with( $string, 'LR\Filters' ) ) {
            $string = \strstr( $string, '(' );
            $string = \strchr( $string, ')', true );
        }

        return \trim( $string, " \n\r\t\v\0;()" );
    }

    public function getProviders() : array {
        return [
            'component' => $this->componentRuntime,
        ];
    }
}