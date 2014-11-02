<?php

/*
|--------------------------------------------------------------------------
| Register The Artisan Commands
|--------------------------------------------------------------------------
|
| Each available Artisan command must be registered with the console so
| that it is available to be called. We'll register every command so
| the console gets access to each of the command object instances.
|
*/

Artisan::resolve('SendRecurringInvoices');
Artisan::resolve('CreateRandomData');
Artisan::resolve('ResetData');
Artisan::resolve('ImportTimesheetData');
