<?php

namespace TheHarvester\QuriLaravel;

use BkvFoundry\Quri\Exceptions\ValidationException;
use BkvFoundry\Quri\Parsed\Expression;
use BkvFoundry\Quri\Parsed\Operation;
use BkvFoundry\Quri\Parser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Platform\Models\SearchableModel;

class QuriRequest extends Request
{
    protected $fieldConfig = [];
    protected $primaryTable;

    /**
     * @param Model|Builder $model
     * @return Model
     */
    public function search($model)
    {
        if ($model instanceof SearchableModel) {
            $this->fieldConfig = $model->searchableFields();
            $this->primaryTable = $model->getTable();
        }

        if ($model instanceof Relation) {
            $this->primaryTable = $model->getModel()->getTable();
            $this->fieldConfig = $model->getModel()->searchableFields();
            $this->apply($model->getQuery());
            return $model;
        }

        return $this->apply($model);
    }

    /**
     * Init and apply the query filter based off the 'q' get parameter
     *
     * @param Builder|Model $builder
     * @return Builder|Model|void
     */
    public function apply($builder)
    {
        if (!array_key_exists('q', $_GET)) {
            return $builder;
        }
        $results = Parser::initAndParse($_GET['q']);

        $builder = $builder->where(function ($builder) use ($results) {
            $this->applyExpressions($builder, $results);
        });
        return $this->applyJoinsFromExpression($builder, $results);
    }

    /**
     * Apply expressions to a laravel query builder
     *
     * @param Builder $builder
     * @param Expression $expression
     * @return Builder
     * @throws ValidationException
     */
    public function applyExpressions($builder, Expression $expression)
    {
        $andOr = $expression->getType();

        if ($nestedExpressions = $expression->nestedExpressions()) {
            $builder = $builder->where(function ($builder) use ($nestedExpressions) {
                foreach ($nestedExpressions as $expression) {
                    $this->applyExpressions($builder, $expression);
                }
            }, null, null, $andOr);
        }
        if ($operations = $expression->operations()) {
            foreach ($operations as $operation) {
                $builder = $this->applyOperation($builder, $operation, $andOr);
            }
        }
        return $builder;
    }

    /**
     * Apply joins to builder based off the requested expression
     *
     * @param Builder $builder
     * @param Expression $expression
     * @return Builder
     * @throws ValidationException
     */
    public function applyJoinsFromExpression($builder, Expression $expression)
    {
        if ($nestedExpressions = $expression->nestedExpressions()) {
            $builder = $this->applyJoinsFromExpression($builder, $expression);
        }
        if ($operations = $expression->operations()) {
            foreach ($operations as $operation) {
                $builder = $this->performJoins($builder, $operation->fieldName());
            }
        }
        return $builder;
    }

    /**
     * Append where for a Quri operation
     *
     * @param Builder $builder
     * @param Operation $operation
     * @param $andOr
     * @return Builder
     * @throws ValidationException
     */
    public function applyOperation($builder, Operation $operation, $andOr)
    {
        $fieldName = $this->getRealFieldName($operation->fieldName());

        switch ($operation->operator()) {
            case "eq":
                $symbol = "=";
                break;
            case "neq":
                $symbol = "!=";
                break;
            case "gt":
                $symbol = ">";
                break;
            case "lt":
                $symbol = "<";
                break;
            case "gte":
                $symbol = ">=";
                break;
            case "lte":
                $symbol = "<=";
                break;
            case "like":
                $symbol = "like";
                break;
            case "between":
                return $builder->whereBetween($fieldName, $operation->values(), $andOr);
            case "in":
                return $builder->whereIn($fieldName, $operation->values(), $andOr);
            case "nin":
                return $builder->whereNotIn($fieldName, $operation->values(), $andOr);
            default:
                throw new ValidationException("QURI string could not be parsed. Operator '{$operation->operator()}' not supported");
        }
        return $builder->where($fieldName, $symbol, $operation->firstValue(), $andOr);
    }

    /**
     * Gets the real field name from the field name passed in from the user
     *
     * @param $fieldName
     * @return string
     * @throws \Exception
     */
    public function getRealFieldName($fieldName)
    {
        if (false === strpos($fieldName, '.')) {
            if (!array_key_exists($fieldName, $this->fieldConfig) || !is_scalar($this->fieldConfig[$fieldName])) {
                throw new \Exception('Field name not supported');
            }
            return "{$this->primaryTable}.{$fieldName}";
        }

        list($related, $relatedFieldName) = explode('.', $fieldName, 2);

        if ($related == $this->primaryTable) {
            // Run it back through the method without having to duplicate logic
            return $this->getRealFieldName($relatedFieldName);
        }

        if (!array_key_exists($related, $this->fieldConfig)) {
            throw new \Exception('Table relationship not supported');
        }

        /** @var Relation $relation */
        $relation = $this->fieldConfig[$related];
        $relatedModel = $relation->getParent();
        $settings = $relatedModel->searchableFields();

        if (!array_key_exists($relatedFieldName, $settings) || !is_scalar($settings[$relatedFieldName])) {
            throw new \Exception('Field name not supported');
        }

        return "{$related}.{$relatedFieldName}";
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder $builder
     * @param string $fieldName
     * @return Builder
     * @throws \Exception
     */
    public function performJoins($builder, $fieldName)
    {
        if (false === strpos($fieldName, '.')) {
            return $builder;
        }

        list($related, $relatedFieldName) = explode('.', $fieldName, 2);

        if ($related == $this->primaryTable || !array_key_exists($related, $this->fieldConfig)) {
            return $builder;
        }

        /** @var Relation $relation */
        $relation = $this->fieldConfig[$related];

        // TODO get these wheres working
//        $builder->getQuery()->mergeWheres(
//            $relation->getQuery()->getQuery()->wheres,
//            $relation->getQuery()->getQuery()->getRawBindings()
//        );
        $builder->getQuery()->joins = array_merge(
            $builder->getQuery()->joins ?: [],
            $relation->getQuery()->getQuery()->joins
        );

        if ($relation instanceof BelongsToMany) {
            $builder->getQuery()->join(
                $relation->getParent()->getTable(),
                $relation->getOtherKey(),
                '=',
                $relation->getParent()->getTable() . '.id'
            );
        } else {
            throw new \Exception('Relation \'' . get_class($relation) . '\' is not yet supported.');
        }
        return $builder;
    }
}
