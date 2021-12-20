# laravel-livewire-remember-state
This trait can be added to your existing laravel-livewire component class to enable state saving. It allows you to specify what properties of the component to track and also exposes 2 methods for moving between states.

Laravel-Livewire is fairly simple and easy to use, but it <strong>doesn’t store state</strong>. Meaning, when a user refreshes the page, they lose whatever state the component was in before the refresh. Meaning, properties value set by the user in the course of interacting with your component will be lost.

Let’s take, for example, a single page application built with livewire that changes the content of the page based on the user’s input. Now with laravel-livewire, if the user does some changes and refreshes the page, the component properties go back to their initial value and the user loses their current state.

With the rememberState trait, you can easily prevent this from happening

# Usage
In your components mount method. Call the **trackAndInit()** method to start tracking.  (**You can track public,private or protected.**)

    $this->trackAndInit([
                  'your_var_name'=>'inital value',
                  'your_var_name_2'=>'inital value',
                  'your_var_name_3'=>'inital value'
               ]);
               

Now the state of your component will be tracked. You can use the trait in multiple components on the same page and each component’s state will be saved separately. Components state are store in ** _livewire_component_states** session variable.

There are two methods available for moving between a components saved state:
**goBackward()** & **goforward()** . You can call this method when a user clicks on a breadcrumb link or a custom back or forward button on your rendered view.

**Note** : The goBackward() and goFoward() method work per component, that means if you have multiple components on a single page, the goBackward() and goForward() method might not function as expected.

# options
- set the number of state changes to keep track of,
- go forward or backward between states,
- have the component go back to the initial state after a certain number of page refreshes,
- specify the properties to track (be it private, protected or public) and set their initial values.

In your component’s mount method, you can set the following options before calling the trackAndInit function . This function starts the tracking process.


    $this->maxTrails = 10; //number of state changes to keep track of
    $this->component = 'track'; //prefix of component name in session
    $this->refreshAtXPageRefresh = 3;//reset properties to their inital values after the 3rd page refresh
    
    
    
# How the withRememberState trait work
[ How to save state with laravel-livewire ](https://soltutorials.com/how-to-save-state-with-laravel-livewire/)
