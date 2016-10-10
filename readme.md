# XML Directory Creator for Yealink & Netsapiens
This simple script was made to automatically generate XML directories for Yealink phones that are connected to the Netsapiens platform. It will automatically create a directory that includes the full name and extension for each user in a domain. It will output a different directory for each domain.

This script is nearly identical to my [XML Enterprise Directory Creator](https://github.com/nesb0t/yealink-enterprise-directory-creator) which generates directories for all external contacts (aka company contacts or enterprise contacts) that a company wishes to share on all phones. The difference is this one generates directories for all internal users only.

# Motivation
While Netsapiens does have a directory creator, it doesn't work how I needed it to with Yealink phones. Users on our previous phone system were used to having a directory on the phone that listed all internal users, and we wanted to provide that same functionality on Netsapiens.

This type of feature was posted as a feature request on the Netsapiens forum ([#545](https://forum.netsapiens.com/t/link-contacts-in-web-portal-to-show-on-yealink-directory/545/)) so I decided to release this for all to use. **I have added a TON of comments to the code, and broken it up in to several sections to make it easy to read**. Even those with very little PHP experience should be able to modify this to work for you.

# Important Note Before Using
The #1 thing that is likely to cause people trouble is found on line #125:
```php
if(isset($value->srv_code) && $value->srv_code == SRV_CODE)
```
This line is doing two things to see if a user should be added to the directory. First it checks if there is any value in the srv_code field, and then it checks if that value is set to "active-user". If either of those are false, then the user will not be added. I have it setup this way because our internal billing tool tracks billable users with the "active-user" service code, and we don't want non-billable users to show up in the directory. 

Ensure you read the setup instructions to get this part of the setup correct. You can easily change the service code it's looking for, or disable that feature altogether, but you must pick one or the other before things will work correctly. 

# Installation and Usage
1. Read the section above prior to continuing, or you will probably have blank directories.
2. Open yealink-directory-creator.php in your favorite text editor (don't use Notepad) and make the necessary changes to the items found in the header. The actual file contains comments next to each item to help you out.
```php
define("SERVER", "nms.example.com");
define("SUPERUSER", "directorycreator@example.com");
define("PASSWORD", "Strong-Password-Here");
define("CLIENTID", "Example_API_User");
define("CLIENTSECRET", "ExampleKey123");
define("DIRECTORYLOCATION", "/var/www/html/example/");

define("SRV_CODE", "example-service-code");
```
  The last one in the list, SRV_CODE, is the one that was discussed above. If you use service codes then you can simply replace "example-service-code" with the code that corresponds to users you want in the directory. If you do not use service codes then comment this line out entirely, and it won't be used. If you leave the example in there, or enter a service code that you don't actually use, you will have blank directories. 
3. Place yealink-directory-creator.php in a non-web-accessible folder on any server that has PHP available.
4. Configure a cron job (Linux) or Scheduled Task (Windows) to run yealink-directory-creator.php automatically every hour (or however often you want).
```sh
0 * * * * php -q -f /home/example/protected/directory-creator/yealink-directory-creator.php > /dev/null 2>&1
```
- The directories will be output to the folder specified in DIRECTORYLOCATION. You will want this to be web accessible. Ensure the user running the cron job has permissions to write to that folder. 
- See the **Security** and **Troubleshooting** sections below for more tips and help.

# Deploying to Yealink Phones
Once the directories are created, you must deploy them to the phones using the remote directory feature. We do it in the NDP via domain overrides. If Netsapiens accepts feature request [#425](https://forum.netsapiens.com/t/ndp-adding-tokens-variables-to-overrides/425) then this will be even easier. If you want to thank me for this script, please go vote for it. :)

```php
remote_phonebook.data.1.url="http://example.com/directory/Example.com.xml"
remote_phonebook.data.1.name="Example"
features.remote_phonebook.enable="1"
features.remote_phonebook.flash_time="3600"
directory_setting.url="http://example.com/directory/favorite_setting.xml"
```

Review Yealink documentation if you have any questions about this, and feel free to contact me if you're still stuck. With additional overrides you configure it so that it goes directly to this phonebook and disable the local phonebook entirely, as well as a few more things.

# Troubleshooting
- There are a number of items that are commented out by the pound sign (#) rather than my usual //. These items will also have a trailing comment that reads "*// Uncomment for basic debugging*." If you are having trouble getting this to work, you can uncomment those lines as necessary to figure out where it may be failing. Just run the file from the command line or make it web-accessible in your local environment and access it from your web browser.
- You may contact me if you have any additional questions.

# Tests
- PHP: Tested on PHP version 5.6.23. I have not tested on v7, but it should be fine.
- OS: Windows and Linux (Debian and Ubuntu).
- Phones: Tested on Yealink T23G, T41P, T42G, T46G, and T48G. Any model that supports their XML directories should be fine.

# Security
- Do not store the PHP script itself in a web-accessible folder. That is a huge risk to your API keys and passwords.
- Setup htaccess on the folder that the directories are written to (DIRECTORYLOCATION) and limit access to them. Some (non-foolproof) suggestions are to lock it down to certain IP addresses and/or to Yealink User-Agents. You can also obfuscate the file/folder names if you're concerned about someone scanning for them.
- The account that is used to access the API should be a Read Only Super User rather than just Super User.

# Contributors and Thanks
- I used Chris Aaker's doCurl function from [here](https://github.com/aaker/domain-selfsignup). Thanks, Chris!

# Version History
v3.1.1 (current): 2016-10-10 @ 6:30pm EST
- Moved the service code feature to the header section so it can be easily used or disabled without having to add or remove sections of code.
- Added a user counter so that if a directory has 0 users, it won't even generate the directory. It was previously creating a blank one.
- Added some additional "debugging" lines.
- Added some details to the header section of the script, including version number, so that it's easy to know which one you have. 

v3.1.0: 2016-10-10 @ 12:00pm EST
- Initial release

# Disclaimer
I am not a developer by any means. I barely knew PHP when I started this. I can't promise that this won't cause any problems for you, up to and including your web server catching on fire. Use it at your own risk. You should test it on a sandbox before you use it on your production servers.

With that said, we have been using it for over a year without any issues. The API calls are all read-only, and you should be using a read-only account anyways, so it should be fine.

# License

I am releasing it under the MIT license which means you are welcome to use it for any and all purposes as long as you do not hold me liable. License details are below. You may contact me if you need it released under a different license.

MIT License

Copyright (c) 2016 Brent Nesbit

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.