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

Package extends `WorkflowBlueprint` with default permissions to perform transitions.

```php
class PostWorkflow extends WorkflowBlueprint
{
    public function getPermission($target, $role)
    {
        // Admin may perform any transitions
        if ($role == 'Role\Admin') {
            return true;
        }
        // Other rules will be provided by RPAC
        return null;
    }
}
```