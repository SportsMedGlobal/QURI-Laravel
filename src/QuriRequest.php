<?php

namespace App\Http\Requests;

use BkvFoundry\Quri\Parsed\Expression;
use BkvFoundry\Quri\Parsed\Operation;
use BkvFoundry\Quri\Parser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class QuriRequest extends Request
{
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
     * @param Builder|Model $builder
     * @param Expression $expression
     * @return Builder|Model
     * @throws \Exception
     */
    public function applyExpressions($builder, Expression $expression)
    {
        $and_or = $expression->getType();

        if ($nested_expressions = $expression->getChildExpressions()) {
            $builder = $builder->where(function ($builder) use ($nested_expressions) {
                /** @var Expression $expression */
                foreach ($nested_expressions as $expression) {
                    $this->applyExpressions($builder, $expression);
                }
            }, null, null, $and_or);
        }
        if ($operations = $expression->getChildOperations()) {
            /** @var Operation $operation */
            foreach ($operations as $operation) {
                $builder = $this->apendWhereByOperation($builder, $operation, $and_or);
            }
        }
        return $builder;
    }

    /**
     * Append where for a Quri operation
     *
     * @param $builder
     * @param Operation $operation
     * @param $and_or
     * @throws \Exception
     */
    protected function apendWhereByOperation($builder, Operation $operation, $and_or)
    {
        switch ($operation->getOperator()) {
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
            case "in":
                return $builder->whereIn($operation->getFieldName(), $operation->getValues(), $and_or);
                return;
            case "nin":
                return $builder->whereNotIn($operation->getFieldName(), $operation->getValues(), $and_or);
                return;
            default:
                throw new \Exception("Blah blah blah");
        }
        $value = $operation->getValues();
        $value = current($value);
        return $builder->where($operation->getFieldName(), $symbol, $value, $and_or);
    }
}
