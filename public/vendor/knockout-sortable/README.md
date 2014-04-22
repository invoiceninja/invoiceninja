**knockout-sortable** is a binding for [Knockout.js](http://knockoutjs.com/) designed to connect observableArrays with jQuery UI's sortable functionality.  This allows a user to drag and drop items within a list or between lists and have the corresponding observableArrays updated appropriately.

**Basic Usage**

* using anonymous templates:

```html
<ul data-bind="sortable: items">
  <li data-bind="text: name"></li>
</ul>
```


* using named templates:

```html
<ul data-bind="sortable: { template: 'itemTmpl', data: items }"></ul>
<script id="itemTmpl" type="text/html">
  <li data-bind="text: name"></li>
</script>
```

Note: The sortable binding assumes that the child "templates" have a single container element. You cannot use containerless bindings (comment-based) bindings at the top-level of your template, as the jQuery draggable/sortable functionality needs an element to operate on.

**Additional Options**

* **connectClass** - specify the class that should be used to indicate a droppable target.  The default class is "ko_container".  This value can be passed in the binding or configured globally by setting `ko.bindingHandlers.sortable.connectClass`.

* **allowDrop** - specify whether this container should be a target for drops.  This can be a static value, observable, or a function that is passed the observableArray as its first argument.  If a function is specified, then it will be executed in a computed observable, so it will run again whenever any dependencies are updated.  This option can be passed in the binding or configured globally by setting `ko.bindingHandlers.sortable.allowDrop`.

* **beforeMove** - specify a function to execute prior to an item being moved from its original position to its new position in the data.  This function receives an object for its first argument that contains the following information:
    * `arg.item` - the actual item being moved
    * `arg.sourceIndex` - the position of the item in the original observableArray
    * `arg.sourceParent` - the original observableArray
    * `arg.sourceParentNode` - the container node of the original list
    * `arg.targetIndex` - the position of the item in the destination observableArray
    * `arg.targetParent` - the destination observableArray
    * `arg.cancelDrop` - this defaults to false and can be set to true to indicate that the drop should be cancelled.

    This option can be passed in the binding or configured globally by setting `ko.bindingHandlers.sortable.beforeMove`.  This callback also receives the `event` and `ui` objects as the second and third arguments.

* **afterMove** - specify a function to execute after an item has been moved to its new destination.  This function receives an object for its first argument that contains the following information:
    * `arg.item` - the actual item being moved
    * `arg.sourceIndex` - the position of the item in the original observableArray
    * `arg.sourceParent` - the original observableArray
    * `arg.sourceParentNode` - the container node of the original list.  Useful if moving items between lists, but within a single array.  The value of `this` in the callback will be the target container node.
    * `arg.targetIndex` - the position of the item in the destination observableArray
    * `arg.targetParent` - the destination observableArray

    This option can be passed in the binding or configured globally by setting `ko.bindingHandlers.sortable.afterMove`.  This callback also receives the `event` and `ui` objects as the second and third arguments.

* **dragged** - specify a function to execute after a draggable item has been dropped into a sortable. This callback receives the drag item as the first argument, the `event` as the second argument, and the `ui` object as the third argument. If the function returns a value, then it will be used as item that is dropped into the sortable. This can be used as an alternative to the original item including a `clone` function.

* **isEnabled** - specify whether the sortable widget should be enabled.  If this is an observable, then it will enable/disable the widget when the observable's value changes.  This option can be passed in the binding or configured globally by setting `ko.bindingHandlers.sortable.isEnabled`.

* **options** - specify any additional options to pass on to the `.sortable` jQuery UI call.  These options can be specified in the binding or specified globally by setting `ko.bindingHandlers.sortable.options`.

* **afterAdd, beforeRemove, afterRender, includeDestroyed, templateEngine, as** - this binding will pass these options on to the template binding.

**Draggable binding**

This library also includes a `draggable` binding that you can place on single items that can be moved into a `sortable` collection.  When the item is dropped into a sortable, the plugin will attempt to call a `clone` function on the item to make a suitable copy of it, otherwise it will use the item directly. Additionally, the `dragged` callback can be used to provide a copy of the object, as described above.

* using anonymous templates:

```html
<div data-bind="draggable: item">
  <span data-bind="text: name"></span>
</div>
```

* using named templates:

```html
<div data-bind="draggable: { template: 'itemTmpl', data: item }"></div>
<script id="itemTmpl" type="text/html">
  <span data-bind="text: name"></span>
</script>
```

**Additional Options**

* **connectClass** - specify a class used to indicate which sortables that this draggable should be allowed to drop into.  The default class is "ko_container".  This value can be passed in the binding or configured globally by setting `ko.bindingHandlers.draggable.connectClass`.

* **isEnabled** - specify whether the draggable widget should be enabled.  If this is an observable, then it will enable/disable the widget when the observable's value changes.  This option can be passed in the binding or configured globally by setting `ko.bindingHandlers.draggable.isEnabled`.

* **options** - specify any additional options to pass on to the `.draggable` jQuery UI call.  These options can be specified in the binding or specified globally by setting `ko.bindingHandlers.draggable.options`.


**Dependencies**

* Knockout 2.0+
* jQuery - no specific version identified yet as minimum
* jQuery UI - no specific version identfied yet as minimum

**Touch Support** - for touch support take a look at: http://touchpunch.furf.com/


**Build:** This project uses [grunt](http://gruntjs.com/) for building/minifying.

**Examples** The `examples` directory contains samples that include a simple sortable list, connected lists, and a seating chart that takes advantage of many of the additional options.

**Fiddles**

* simple: http://jsfiddle.net/rniemeyer/hw9B2/
* connected: http://jsfiddle.net/rniemeyer/Jr2rE/
* draggable: http://jsfiddle.net/rniemeyer/AC49j/
* seating chart: http://jsfiddle.net/rniemeyer/UdXr4/



**License**: MIT [http://www.opensource.org/licenses/mit-license.php](http://www.opensource.org/licenses/mit-license.php)
