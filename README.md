# QURI-Laravel

WORK IN PROGRESS

A Laravel request wrapper for the QURI parser.

More information about the QURI spec and PHP implementation [available here.](https://github.com/theHarvester/QURI)

## Basic Usage

The QuriRequest object can be loaded into a route with Laravel's service container.

```
Route::get('/', function (QuriRequest $request, Article $article) {
    $article = $request->apply($article);
    return $article->get();
});
```

A request can be made in the browser using the `q` query parameter.

```
/users/?q=id.eq(123)
```

A slightly more complex query might look like:

Fetch any articles that start with "Breaking News" that are also published after the 20th of December 2015.

```
/articles/?q=(title.like("Breaking News%"),published_date.gte("2015-12-20"))
```

'Or' and nested queries are also supported.

Fetch any contacts which have a status of read_to_by or warm - or any contacts that are over 40 years old and were last contacted on the 10th of November 2015.

```
/contacts/?q=status.in("read_to_by","warm")|(age.lt(40),last_contacted.lt('2015-11-10'))
```
