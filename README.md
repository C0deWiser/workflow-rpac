# Workflow + RPAC

Brings Rpac functionality to Workflow package.

Here you may authorise user requests to update State Machine.

## Installation

Follow instructions from `rpac` and `workflow` packages.

## Usage

Package uses RPAC to keep permissions in database. 
Authorize any requests to your resource controllers using RPAC.
RPAC now is know how to work with Workflow models.

Package provides `WorkflowPolicy` with `transit` authorization method.
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

Also, this policy extends `getPermission` method.

```php
class PostPolicy {
    public function getPermission($action, $workflow = null, $state = null)
    {
        // On `new` state Author can do anything with his Post
        if ($state = 'new') {
            return 'App\Post\Author';
        }
        // Admin can create and view any record, no matter the state
        if (!$workflow || $action == 'view') {
            return 'Role\Admin';
        }
        // Other rules will be provided by RPAC
    }
}
```

Package extends `WorkflowBlueprint` with default permissions to perform transitions.

```php
class PostWorkflow extends WorkflowBlueprint
{
    public function getPermission($source, $target)
    {
        // Admin may perform any transitions
        return 'Role\Admin';
        // Other rules will be provided by RPAC
    }
}
```