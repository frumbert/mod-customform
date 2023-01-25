Custom Form module
=============

The Custom Form displays fields from a given category of custom fields for the module.

When the user submits the page, it collects the form data and posts it to a the configured URL (usually external), then shows the user some feedback.

**No data** is recorded in the Moodle database (the value(s) that your users enter will not get stored into the customfield_data table.)

## Set up

1. A site admin needs to set up the custom fields. This is under Site Administration > Plugins > Activity Modules > Customfields for CustomForm activity.
2. Add an instance of CustomForm to your course/topic.

After adding your instance you need to configure:

- Feedback : The message to show to the user after submission
- URL : The external URL to post the data to (uses curl and application/json)
- Category : The category containing the custom profile fields to show

## Default values

You can add these tokenisers as default values to your custom form fields. At runtime, these will be replaced with their calculated values.

* `VALUE:USER:FIRSTNAME` - User first name
* `VALUE:USER:LASTNAME` - User last name
* `VALUE:USER:EMAIL` - User email
* `VALUE:USER:FULLNAME` - User fullname (firstname lastname)
* `VALUE:USER:FULLNAMEALT` - User fullname (firstname middlename alternatename lastname)
* `VALUE:COURSE:FULLNAME` - Course long name
* `VALUE:COURSE:SHORTNAME` - Course short name
* `VALUE:SITE:WWWROOT` - URL of site
* `VALUE:PREF:pref-name` - Returns user preference value named `pref-name` for the current user

## TODO

* Support multipart/form-data if files/images are present in the field and perform uploads of the contents

## Licence

GPL3