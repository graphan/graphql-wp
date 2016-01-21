<?php
namespace TheFold\GraphQLWP;

use \GraphQL\Type\Definition\InterfaceType;
use \GraphQL\Type\Definition\ObjectType;
use \GraphQL\Type\Definition\Type;
use \GraphQL\Type\Definition\IDType;
use \GraphQL\Type\Definition\ListOfType;
use \GraphQL\Type\Definition\EnumType;

use \TheFold\GraphQLWP\Type\Definition\MenuItem;

class Schema
{
    protected $postInterface = null;
    protected $query = null;
    protected $wpQuery = null;
    protected $term = null;
    protected $postStatus = null;

    protected $types = [];

    function __construct() {
        foreach($this->getPostTypes() as $type => $schema) {
            $this->addPostType($type, $schema);
        } 
    }

    function getPostTypes(){
        return apply_filters('graphql-wp/get_post_types',[
            'post'=> [
                'name' => 'Post',
                'description' => 'A standard WordPress post.',
            ],
            'page'=> [
                'name' => 'Page',
                'description' => 'A standard WordPress page.',
            ]
        ],$this);
    }

    function addPostType($type, $schema){

        $schema['fields'] = array_replace_recursive(
            $this->getPostInterfaceSchema()['fields'],
            (array) $schema['fields']
        );

        $schema['interfaces'] = [$this->getPostInterface()];

        if(!isset($schema['isTypeOf'])){
            $schema['isTypeOf'] = function($obj) use ($type) {
                return ($obj->post_type == $type || $type == 'wp_post' && $obj->post_type == 'post');
            };
        }

        $this->types[$type] = new ObjectType($schema);
    }

    function build() {
        return new \GraphQL\Schema($this->getQuery());
    }
    
    function getPostInterface() {
        return $this->postInterface ?: $this->postInterface = new InterfaceType( $this->getPostInterfaceSchema() );
    }

    function getPostInterfaceSchema() {

        return apply_filters('graphql-wp/get_post_interface_schema',[
            'name' => 'WP_Post',
            'description' => 'The WP_Post class is used to contain post objects stored by the database',
            'fields' => [
                'ID' => [
                    'type' => Type::nonNull(Type::string()),
                    'description' => 'The ID of the post',
                ],
                'name' => [
                    'type' => Type::string(),
                    'description' => 'The post\'s slug',
                    'resolve' => function($post) {
                        return $post->post_name;
                    }
                ],
                'title' => [
                    'type' => Type::string(),
                    'description' => 'The title of the post',
                    'resolve' => function($post) {
                        return get_the_title($post);
                    }
                ],
                'content' => [
                    'type' => Type::string(),
                    'description' => 'The full content of the post',
                    'resolve' => function($post) {
                        return apply_filters('the_content', get_post_field('post_content', $post));
                    }
                ],
                'excerpt' => [
                    'type' => Type::string(),
                    'description' => 'User-defined post except',
                    'resolve' => function($post) {
                        return apply_filters('the_content', get_post_field('post_excerpt', $post));
                    }
                ],
                'date' => [
                    'type' => Type::string(),
                    'description' => 'Format: 0000-00-00 00:00:00',
                    'resolve' => function($post) {
                        return $post->post_date;
                    }
                ],
                'date_gmt' => [
                    'type' => Type::string(),
                    'description' => 'Format: 0000-00-00 00:00:00',
                    'resolve' => function($post) {
                        return $post->post_date_gmt;
                    }
                ],
                'status' => [
                    'type' => $this->getPostStatus(),
                    'description' => 'Status of the post',
                    'resolve' => function($post) {
                        return $post->post_status;
                    }
                ],
                'parent' => [
                    'type' => function() {
                        return $this->getPostInterface();
                    },
                    'description' => 'Parent of this post',
                    'resolve' => function($post) {
                        return $post->post_parent ? get_post($post->post_parent) : null;
                    }
                ],
                'modified' => [
                    'type' => Type::string(),
                    'description' => 'Format: 0000-00-00 00:00:00',
                    'resolve' => function($post) {
                        return $post->post_modified;
                    }
                ],
                'modified_gmt' => [
                    'type' => Type::string(),
                    'description' => 'Format: 0000-00-00 00:00:00',
                    'resolve' => function($post) {
                        return $post->post_modified_gmt;
                    }
                ],
                'comment_count' => [
                    'type' => Type::int(),
                    'description' => 'Number of comments on post',
                    'resolve' => function($post) {
                        return $post->comment_count;
                    }
                ],
                'menu_order' => [
                    'type' => Type::int(),
                    'description' => 'Which movies they appear in.',
                    'resolve' => function($post) {
                        return $post->menu_order;
                    }
                ],
                'terms' => [
                    'type' => new ListOfType($this->getTerm()),
                    'description' => 'Terms ( Categories, Tags etc ) or this post',
                    'args' => [
                        'taxonomy' => [
                            'description' => 'The taxonomy for which to retrieve terms. Defaults to post_tag.',
                            'type' => Type::string(),
                        ],
                        'orderby' => [
                            'description' => "Defaults to name",
                            'type' => Type::string(),
                        ],
                        'order' => [
                            'description' => "Defaults to ASC",
                            'type' => Type::string(),
                        ]
                    ],
                    'resolve' => function($post, $args) {

                        $args += [
                            'taxonomy' => null,
                            'orderby'=>'name',
                            'order' => 'ASC',
                        ];
                        extract($args);

                        $res = wp_get_post_terms($post->ID, $taxonomy, ['orderby'=>$orderby,'order'=>$asc]); 

                        return is_wp_error($res) ? [] : $res;
                    }
                ]
            ],
            'resolveType' => [$this, 'resolvePostType']
        ], $this);
    }

    function resolvePostType($post) {
        return apply_filters('graphql-wp/resolve_post_type', isset($this->types[$post->post_type]) 
            ? $this->types[$post->post_type] 
            : $this->types['post'] 
        );
    }

    function getPost() {
        return $this->getType('post');
    }

    function getType($type) {
        return $this->types[$type];
    }

    function getPostStatus() {

        return $this->postStatus ?: $this->postStatus = new EnumType($this->getPostStatusSchema());
    }

    function getPostStatusSchema() {

        return apply_filters('grapql-wp/get_post_status_schema',[
            'name' => 'PostStatus',
            'description' => 'A valid post status',
            'values' => [
                'publish' => [
                    'value' => 'publish',
                    'description' => 'A published post or page'
                ],
                'pending' => [
                    'value' => 'pending',
                    'description' => 'post is pending review'
                ],
                'draft' => [
                    'value' => 'draft',
                    'description' => 'a post in draft status'
                ],
                'autodraft' => [ 
                    'name' => 'autodraft',
                    'value' => 'auto-draft',
                    'description' => 'a newly created post, with no content'
                ],
                'future' => [
                    'value' => 'future',
                    'description' => 'a post to publish in the future',
                ],
                'private' => [
                    'value' => 'private',
                    'description' => 'not visible to users who are not logged in'
                ],
                'inherit' => [
                    'value' => 'inherit',
                    'description' => 'a revision.'
                ],
                'trash' => [
                    'value' => 'trash',
                    'description' => 'post is in trashbin'
                ]
            ]
        ], $this);
    }

    function getTerm() {
        return $this->term ?: $this->term = new ObjectType($this->getTermSchema());
    }

    function getTermSchema() {
        
        return apply_filters('grapql-wp/get_term_schema',[
            'name' => 'WP_Term',
            'description' => 'Base class for taxonomies such as Category & Tag',
            'fields' => [
                'term_id' => ['type' => Type::string()],
                'name' => ['type' => Type::string()],
                'slug' => ['type' => Type::string()],
                'term_taxonomy_id' => ['type' => Type::string()],
                'taxonomy' => ['type' => Type::string()],
                'description' => ['type' => Type::string()],
                'parent' => [
                    'type' => function(){
                        return $this->getTerm();
                    }
                ]
            ]
        ], $this);
    }

    function getWPQuery() {
        return $this->wpQuery ?: $this->wpQuery = new ObjectType($this->getWPQuerySchema());
    }

    function getWPQuerySchema() {
        return [
            'name' => 'WPQuery',
            'description' => 'deals with the intricacies of a post request on a WordPress blog',
            'fields' => [
                'posts' => [
                    'type' => new ListOfType($this->getPostInterface()),
                    'args' => [
                        'posts_per_page' => [
                            'description' => 'number of post to show per page',
                            'type' => Type::int(),
                        ],
                        'post_type' => [
                            'description' => "Retrieves posts by Post Types, default value is 'post'.",
                            'type' => new ListOfType(Type::string()),
                        ],
                        'name' => [
                            'description' => "Retrieves post by name",
                            'type' => Type::string(),
                        ],
                        'order' => [
                            'description' => "Designates the ascending or descending order of the 'orderby' parameter. Defaults to 'DESC'. An array can be used for multiple order/orderby sets.",
                            'type' => Type::string()
                        ],
                        'orderby' => [
                            'description' => "Sort retrieved posts by parameter. Defaults to 'date (post_date)'. One or more options can be passed.",
                            'type' => Type::string()
                        ],
                        's' => [
                            'description' => "Show posts based on a keyword search.",
                            'type' => Type::string()
                        ],
                        'cat' => [
                            'description' => "Show in this category id",
                            'type' => Type::int()
                        ],
                        'category_name' => [
                            'description' => "Show in this category slug",
                            'type' => Type::string()
                        ],
                        'tag' => [
                            'description' => "Show in this tag slug",
                            'type' => Type::string()
                        ],
                        'tag_id' => [
                            'description' => "Show in this tag id",
                            'type' => Type::int()
                        ]
                    ],
                    'resolve' => function($root, $args) {
                        return $args ? get_posts($args) : $root->posts;
                    }
                ],
                'menu' => [
                    'type' => new ListOfType( new MenuItem(['name'=>'MenuItem']) ),
                    'args' => [
                        'name' => [
                            'type' => Type::string(),
                            'description' => "Menu 'id','name' or 'slug'"
                        ]
                    ],
                    'resolve' => function($root, $args) {
                        return wp_get_nav_menu_items($args['name']);
                    }
                ],
                'home_page' => [
                    'type' => $this->getPostInterface(),
                    'resolve' => function(){
                        return get_post(get_option('page_on_front'));
                    }
                ]
            ]
        ];
    }

    function getQueryArgsPost() {
        return [
            'ID' => [
                'name' => 'ID',
                'description' => 'id of the post',
                'type' => Type::string()
            ],
            'slug' => [
                'name' => 'slug',
                'description' => 'name of the post',
                'type' => Type::string()
            ],
            'post_type' => [
                'name' => 'post_type',
                'description' => 'type of the post',
                'type' => Type::string()
            ]
        ];
    }

    function postQueryResolve($root, $args) {
        if(isset($args['ID'])){
            return get_post($args['ID']);
        }
        else {
            return get_page_by_path($args['slug'], \OBJECT, isset($args['post_type']) ? $args['post_type'] : 'post');
        }
    }

    function resolvePostMeta($post, $args, $info) {
        return get_post_meta($post->ID, $info->fieldName, true);
    }

    function getQuery() {
        return $this->query ?: $this->query = new ObjectType($this->getQuerySchema());
    }
        
    function getQuerySchema() {

        return apply_filters('graphql-wp/get_query_schema',[
            'name' => 'Query',
            'fields' => [
                'wp_query' => [
                    'type' => $this->getWPQuery(),
                    'resolve' => function($root, $args) {
                        global $wp_query;
                        return $wp_query;
                    } 
                ],
                'wp_post' => [
                    'type' => $this->getPostInterface(),
                    'args' => $this->getQueryArgsPost(),
                    'resolve' => [$this, 'postQueryResolve']
                ],
                'term' => [  // not sure if I need this
                    'type' => $this->getTerm(),
                    'args' => [
                        'id' => [
                            'type' => Type::string(),
                            'desciption' => 'Term id'
                        ]
                    ],
                    'resolve' => function($root, $args) {
                        return get_term($args['id']);
                    }
                ],
            ]
        ], $this);
    }
}