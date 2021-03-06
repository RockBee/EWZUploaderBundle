<?php

namespace EWZ\Bundle\UploaderBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('ewz_uploader');

        $rootNode
            ->children()
                ->booleanNode('load_jquery')->defaultFalse()->end()
                ->booleanNode('generate_unique_name')->defaultFalse()->end()
                ->booleanNode('keep_original_name')->defaultFalse()->end()
                ->scalarNode('default_filename')->defaultValue('filename')->end()

                ->arrayNode('media')
                    ->addDefaultsIfNotSet()
                    ->canBeUnset()
                    ->children()
                        ->scalarNode('max_size')->defaultValue('1024k')->end()
                        ->arrayNode('mime_types')
                            ->prototype('scalar')->defaultNull()->end()
                        ->end()
                        ->scalarNode('dir')->defaultValue('%kernel.root_dir%/../web')->end()
                        ->scalarNode('folder')->defaultValue('uploads')->end()
                        ->scalarNode('image_min_width')->defaultValue('150')->end()
                        ->scalarNode('image_min_height')->defaultValue('150')->end()
                        ->scalarNode('image_max_width')->defaultNull()->end()
                        ->scalarNode('image_max_height')->defaultNull()->end()
                        ->scalarNode('image_show_max_width')->defaultNull()->end()
                        ->scalarNode('image_show_min_width')->defaultValue('180')->end()
                        ->booleanNode('image_resize_to_show')->defaultTrue()->end()
                    ->end()
                ->end()

                ->arrayNode('url')
                    ->addDefaultsIfNotSet()
                    ->canBeUnset()
                    ->children()
                        ->scalarNode('upload')->defaultValue('ewz_uploader_file_upload')->end()
                        ->scalarNode('remove')->defaultValue('ewz_uploader_file_remove')->end()
                        ->scalarNode('download')->defaultValue('ewz_uploader_file_download')->end()
                        ->scalarNode('crop')->defaultValue('ewz_uploader_file_crop')->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
