<?php

namespace Neocom\JWK\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class RevokedKeyScope implements Scope
{
    /**
     * All of the extensions to be added to the builder.
     *
     * @var string[]
     */
    protected $extensions = ['WithRevoked', 'WithoutRevoked', 'OnlyRevoked'];

    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    public function apply(Builder $builder, Model $model)
    {
        $builder->whereNull($model->getQualifiedRevokedAtColumn());
    }

    /**
     * Extend the query builder with the needed functions.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @return void
     */
    public function extend(Builder $builder)
    {
        foreach ($this->extensions as $extension) {
            $this->{"add{$extension}"}($builder);
        }
    }

    /**
     * Get the "revoked at" column for the builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @return string
     */
    protected function getRevokedAtColumn(Builder $builder)
    {
        if (count((array) $builder->getQuery()->joins) > 0) {
            return $builder->getModel()->getQualifiedRevokedAtColumn();
        }

        return $builder->getModel()->getRevokedAtColumn();
    }

    /**
     * Add the with-revoked extension to the builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @return void
     */
    protected function addWithRevoked(Builder $builder)
    {
        $builder->macro('withRevoked', function (Builder $builder, $withRevoked = true) {
            if (! $withRevoked) {
                return $builder->withoutRevoked();
            }

            return $builder->withoutGlobalScope($this);
        });
    }

    /**
     * Add the without-revoked extension to the builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @return void
     */
    protected function addWithoutRevoked(Builder $builder)
    {
        $builder->macro('withoutRevoked', function (Builder $builder) {
            $model = $builder->getModel();

            $builder->withoutGlobalScope($this)->whereNull(
                $model->getQualifiedRevokedAtColumn()
            );

            return $builder;
        });
    }

    /**
     * Add the only-revoked extension to the builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @return void
     */
    protected function addOnlyRevoked(Builder $builder)
    {
        $builder->macro('onlyRevoked', function (Builder $builder) {
            $model = $builder->getModel();

            $builder->withoutGlobalScope($this)->whereNotNull(
                $model->getQualifiedRevokedAtColumn()
            );

            return $builder;
        });
    }
}
