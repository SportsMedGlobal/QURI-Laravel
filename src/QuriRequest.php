<?php

namespace TheHarvester\QuriLaravel;

use BkvFoundry\Quri\Exceptions\ValidationException;
use BkvFoundry\Quri\Parsed\Expression;
use BkvFoundry\Quri\Parsed\Operation;
use BkvFoundry\Quri\Parser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Database\Query\Builder;

class QuriRequest extends Request
{
    protected $relatedConfig = [];

    /**
     * @param Model|Builder $privilege
     * @return Model
     */
    public function search($privilege)
    {
        if (method_exists($privilege, "searchableRelationships")) {
            $this->relatedConfig = $privilege->searchableRelationships();
        }

        return $this->apply($privilege);
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
        return $this->applyExpressions($builder, $results);
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
                return $builder->whereBetween($operation->fieldName(), $operation->values(), $andOr);
            case "in":
                return $builder->whereIn($operation->fieldName(), $operation->values(), $andOr);
            case "nin":
                return $builder->whereNotIn($operation->fieldName(), $operation->values(), $andOr);
            default:
                throw new ValidationException("QURI string could not be parsed. Operator '{$operation->operator()}' not supported");
        }
        // joins?
        if (method_exists($this, "validateValues")) {
            // TODO needs some work
            $this->validateValues($operation->fieldName(),$operation->values());
        }
        return $builder->where(
            $this->getRealFieldName($operation->fieldName()),
            $symbol,
            $operation->firstValue(),
            $andOr
        );
    }

    /**
     * Gets the real field name from the field name passed in from the user
     *
     * @param $fieldName
     * @return string
     */
    public function getRealFieldName($fieldName)
    {
        return $fieldName;
    }

}
