# WP Google Sheets
Add info from a google sheet and add it to a Themeco looper. My data has: Date, 1st Place Name, 2nd Place Name, 3rd Place Name, 4th Place Name

**Note:**

I created recurring events in the MEC plugin and this code uses that to Fetch events that are today or have repeating date equal to today or in the future

I am using the Hook Name: `minecraftevents` so make sure to search for `cs_looper_custom_minecraftevents` and update `minecraftevents` to whatever you want and use that for your hook name

Make sure to update the timezone if you are not using EST

You will have to update the JSON file with the correct email and keys

[Using OAuth 2.0 for Web Server Applications](https://github.com/googleapis/google-api-php-client/blob/main/docs/oauth-web.md#using-oauth-20-for-web-server-applications)
