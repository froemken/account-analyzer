# Account Analyzer
This is a very little package to analyze your CSV data of your bank account. Currently it only works with exported CSV of www.ing.de

## Install
You can install this package with:
`composer req stefanfroemken/account-analyzer`

## Configure
There is not really something to configure. Ok, there is a Main.yaml file in Configuration folder where you can change the template paths. I have never tested, if changing these folders will work.

## Requirement
* No Database needed
* PHP 7.1-7.4 should work, but only PHP 7.3 tested
* Because of various .htaccess files you should use Apache Server. Of cause Nginx is possible, but in that case you have to secure Uploads-folder on your own.

## Using this package
Open index.php in your Browser. You will see an Upload Form. Here you can upload the CSV file from your bank. The file will be stored in Uploads-folder which is normally secured by a .htaccess file (only Apache).

### Views
Default view is `Analyze`. Here you will see a complete table with all of your bookings and a total sum.

You can switch to `grouped` view, to see all of your bookings grouped into month (Jan-Dec). Of cause a total of each month will be displayed.

Last view in `year`. Here you can see all totals of each month.

## Clean up
Each view has a link to `Clear Cache`. This button will delete all uploaded files in Uploads-folder directly, no confirmation message appears!
