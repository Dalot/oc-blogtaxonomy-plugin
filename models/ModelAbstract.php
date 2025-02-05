<?php

namespace GinoPane\BlogTaxonomy\Models;

use Model;
use Cms\Classes\Controller;
use October\Rain\Database\Builder;
use October\Rain\Database\Relations\HasMany;
use GinoPane\BlogTaxonomy\Classes\PostListExceptionsTrait;

/**
 * Class ModelAbstract
 *
 * @property string $url
 *
 * @package GinoPane\BlogTaxonomy\Models
 */
abstract class ModelAbstract extends Model
{
    /**
     * @var array
     */
    public static $sortingOptions = [];

    /**
     * Sets the URL attribute with a URL to this object
     *
     * @param string $pageName
     * @param Controller $controller
     * @param array $params
     *
     * @return void
     */
    public function setUrl($pageName, Controller $controller, array $params = array())
    {
        $params = $this->getModelUrlParams($params);

        $this->url = $controller->pageUrl($pageName, $params);
    }

    /**
     * @param array $params
     *
     * @return array
     */
    abstract protected function getModelUrlParams(array $params): array;

    /**
     * Gets a list of items related to Posts for frontend use
     *
     * @param       $query
     * @param array $options Available options are "sort", "displayEmpty", "limit", "post"
     *
     * @return mixed
     */
    public function scopeListFrontend(Builder $query, array $options = [])
    {
        $this->withRelation($query, $options);

        $this->queryOrderBy($query, $options);

        $this->queryDisplayEmpty($query, $options);

        $this->queryPostSlug($query, $options);

        $this->queryLimit($query, $options);

        // GROUP BY is required for SQLite to deal with HAVING
        // We use it for all connections just to keep implementation
        // independent from the connection being used
        $this->queryGroupBy($query);

        return $query->get();
    }

    /**
     * @param Builder   $query
     * @param string    $property
     * @param mixed     $value
     */
    public function scopeWhereTranslatable(Builder $query, string $property, $value)
    {
        self::whereTranslatableProperty($query, $property, $value);
    }

    /**
     * @param Builder $query
     * @param string $property
     * @param $value
     */
    public static function whereTranslatableProperty(Builder $query, string $property, $value)
    {
        $query->getModel()->isClassExtendedWith('RainLab.Translate.Behaviors.TranslatableModel')
            ? $query->transWhere($property, $value)
            : $query->where($property, $value);
    }

    /**
     * @param Builder $query
     * @param array   $options
     *
     * @return void
     */
    private function queryDisplayEmpty(Builder $query, array $options)
    {
        if (empty($options['displayEmpty'])) {
            $query->having('posts_count', '>', 0);
        }
    }

    /**
     * @param Builder $query
     * @param array   $options
     *
     * @return void
     */
    private function queryPostSlug(Builder $query, array $options)
    {
        if (!empty($options['post'])) {
            $query->whereHas(
                'posts',
                static function ($query) use ($options) {
                    ModelAbstract::whereTranslatableProperty($query, 'slug', $options['post']);
                }
            );
        }
    }

    /**
     * @param Builder $query
     * @param array   $options
     *
     * @return void
     */
    private function queryLimit(Builder $query, array $options)
    {
        if (!empty($options['limit'])) {
            $query->take($options['limit']);
        }
    }

    /**
     * @param Builder $query
     * @param array   $options
     *
     * @return void
     */
    private function queryOrderBy(Builder $query, array $options)
    {
        if (!empty($options['sort']) && \array_key_exists($options['sort'], static::$sortingOptions)) {
            if ($options['sort'] === 'random') {
                $query->inRandomOrder();
            } else {
                list($sortField, $sortDirection) = explode(' ', $options['sort']);

                $query->orderBy($sortField, $sortDirection);
            }
        }
    }

    /**
     * @param Builder $query
     * @param array   $options
     *
     * @return void
     */
    private function withRelation(Builder $query, array $options)
    {
        if (!empty($options['fetchPosts'])) {
            $query->with(
                [
                    'posts' => function (HasMany $query) use ($options) {
                        $query->isPublished();

                        $this->handleExceptions($query->getQuery(), $options);
                    }
                ]
            );
        }

        $query->withCount(
            [
                'posts' => function ($query) use ($options) {
                    $query->isPublished();

                    $this->handleExceptions($query, $options);
                }
            ]
        );
    }

    /**
     * @param Builder $query
     */
    private function queryGroupBy(Builder $query)
    {
        $query->groupBy('id');
    }

    /**
     * @param Builder   $query
     * @param array     $options
     */
    private function handleExceptions(Builder $query, array $options)
    {
        if (!empty($options['exceptPosts'])) {
            PostListExceptionsTrait::handleExceptionsByPost($query, $options['exceptPosts']);
        }

        if (!empty($options['exceptCategories'])) {
            PostListExceptionsTrait::handleExceptionsByCategory($query, $options['exceptCategories']);
        }
    }
}
