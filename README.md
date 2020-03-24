# Workflow + RPAC

Merge RPAC functionality with Workflow package.

Here you may authorise user requests to access or change State Machine.

## Installation

Follow instructions from `rpac` and `workflow` packages.

We suppose you have read documentation on `rpac` and `workflow`.

## Usage

Package uses RPAC to keep permissions in database. 
Authorize any requests to your resource controllers using RPAC.
RPAC now is know how to work with Workflow models.

Package provides `WrpacPolicy` with `transit` authorization method.
So you may authorize user attempt to change workflow state.

```php
class Controller
{
    public function update(Request $request, $id)
    {
        $post = Post::find($id);
    
        if ($request->has('workflow')) {
            $this->authorize(
                'transit', 
                [$post, 'workflow', $request->get('workflow')]
            );
        }
    }
}
```

Also, this policy extends `getDefaults` method.

```php
class PostPolicy {
    public function getDefaults($action, $workflow = null, $state = null)
    {
        // On `new` state Author can do anything with his Post
        if ($state = 'new') {
            return 'App\Post\Author';
        }
        // Admin can create and view any record, no matter the state
        if (!$workflow || $action == 'view') {
            return 'admin';
        }
        // Other rules will be provided by RPAC
    }
}
```

Package extends `WorkflowBlueprint` with default permissions to perform transitions.

```php
class PostWorkflow extends WorkflowBlueprint
{
    public function getDefaults($source, $target)
    {
        // Admin may perform any transitions
        return 'admin';
        // Other rules will be provided by RPAC
    }
}
```

## Getting abilities

To build proper User Interface you need to know whether User allowed to change Model State Machine.
You may collect full list of authorized transitions through Model.

```php
$post = Post::find($id);
$abilities = $post->getAuthorizedTransitions(Auth::user(), 'workflow_attr');

// If Model has just one Workflow
$abilities = $post->getAuthorizedTransitions(Auth::user());

// or use property, that returns transitions for authorized user
$abilities = $post->authorizedTransitions;
// [[review, publish, 'You can publish this Post only if Moon is full'], [review, correct]]

```

