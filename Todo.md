## Bug Fixes
- [ ] When we have tasks associated with a project, then archive the tasks, the project's total hours worked are decreased
- [ ] When running the task report with a date range, it captures some dates that are outside that date range. Most likely an issue with converting between UTC and local time. 

## Payroll Admin Feature
- Ability to set "Hourly Rate" on the Vendor under additional info.
- New menu option only accessible to Admins called "Payroll Admin" 
The Payroll admin feature should function similarly to the Task report. It should pull in all of the tasks with time worked in a given date range. Should allow checkboxes to select which tasks we are going to run payroll for (should default to all of them checked). Should also allow me to specify a "Payment Date" (the date that the payroll payments will actually be paid). Once I click a button to run the payroll, it should create an expense for each vendor based on their total hours worked for selected tasks during the period * their hourly pay rate. It should set the expense date to the "Payment Date" I supplied. It should also mark the payment as "Paid" with a payment method of "ACH" and a payment date that I supplied when running the payroll. 