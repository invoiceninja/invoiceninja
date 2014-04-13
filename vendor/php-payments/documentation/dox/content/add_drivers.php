<div class="page-header">
	<h1>Easily add your own driver to PHP-Payments</h1>
</div>
	<div class="row-fluid">
		<div class="span8">
			<h3>Video - Creating Drivers</h3>
			<p>Being uploaded!</p>
			<h3>Why create your own driver?</h3>
			<p>Because you think the idea of an open source PHP project supporting many payment gateways with one API is sexy as hell.  Because you're not a douche.  Because you like writing code and want to sharpen your teeth with payment APIs.  These, and many others, are all excellent reasons to contribute to PHP-Payments.</p>
			<h3>Successfully integrating a new driver into PHP-Payments is all about preparation.  You'll need to complete the following pre-requisites:</h3>
			<ul>
				<li>Have a testing account available for the gateway you want to integrate.</li>
				<li>Have a github profile and create your own fork of the project.</li>
				<li>Carefully examine which methods are supported by your gateway and what format they expect interaction to be in.</li>
				<li>Find out if your gateway has an SDK.  If it's any good, you may be able to just drop it into the vendor folder and write an interface for it.</li>
			</ul>
			<h3>Next, make sure you understand how PHP-Payments works.</h3>
			<p>PHP-Payments relies heavily on __call magic methods and matching methods and keys via arrays.  Taking this approach allowed us to eliminate duplicate code and keep an extremely clear detail of what methods and parameters were supported by each method within each gateway, within the driver itself.  When you instantiate PHP-Payments, the library first autoloads some helper classes such as a utility class, a response class, a request class, the payment driver abstract class.  When you call a method, PHP-Payments make sure the driver and method you're using exists, and via the method_map() function which is implemented in each driver, ensures you've submitted the required keys for that method.  If all checks out, the request can be made.</p>
			<h3>Hopefully you have a good feel for how PHP-Payments works.  If you need any help, you can always create an issue on Github or add Calvin on Skype.  His handle is halcyon-sky.</h3>
		</div>