<?php

namespace Mohiohio\GraphQLWP\Type\Definition;

use \Mohiohio\GraphQLWP\Schema as WPSchema;

class Post extends WPObjectType {

    static function getDescription() {
        return 'A standard WordPress blog post';
    }

    static function getFieldSchema() {
        return WPPost::getFieldSchema();
    }

    static function getSchemaInterfaces() {
        \Analog::log('schema intefraces for post');
        return [WPPost::getInstance(), WPSchema::getNodeDefinition()['nodeInterface']];
    }
}
