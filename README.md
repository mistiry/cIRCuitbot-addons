# Addons for cIRCuitbot
This repo contains addon-ons (triggers and modules) for [cIRCuitbot](https://github.com/mistiry/cIRCuitbot). You can clone this repo, and then move the addons you want (or, all of them) into the requisite path, and load them in your bot configuration file. Some triggers or modules may require additional installation steps - check the README for more information.

## Structure

Both triggers and modules follow the same basic structure. At the root, you have a folder named whatever you want to call the addon. Under that, at least 3 files should exist - for triggers, those would be `trigger.conf`, `trigger.php`, and `README.md`. 

```
$BotInstallationDirectoy
|
└── modules
|	|
|	└── nameOfModule
|			module.conf
|			module.php
|			README.md
|		 
└── triggers
	|
	└── nameOfTrigger
			trigger.conf
			trigger.php
			README.md
		 
```

### Loading

In your bot configuration file, add the triggers and modules you want to use in the proper section (see `example.conf` for more help). Using the above structure tree as an example, the name to use in your configuration file would be `nameOfTrigger` or `nameOfModule`. 


### [trigger|module].conf

This file MUST contain at least the following:

**Triggers:**
`triggerWord` is the word or phrase to trigger on. This is NOT case-sensitive.
`nameOfFunction` is the name of the PHP function (contained in `trigger.php`) that is called when `triggerWord` is seen.
```
trigger[] = "triggerWord||nameOfFunction"

```

**Modules:**
`command` is the command that is called by users in chat. DO NOT include a command prefix here, that is set in your bot's config file. This is the word that follows your command prefix, for example if your command prefix is `!` you would use `seen` as the value for `command` below if you wanted users to be able to run the `!seen` command.
`nameOfFunction` is the name of the PHP function (contained in `module.php`) that is called when a user gives the `command`.
```
module[] = "command||nameOfFunction"

```


## Writing New Addons

As you can see from the provided test trigger and module files, there is very little to making new functionality for the bot. The key items to remember are:

1. You **MUST** follow the proper structure and have the necessary files in your addon.
2. You can include additional configuration options in the `.conf` file and load them into your function by calling the `parse_ini_file()` PHP function. The trigger `doActionFromWord` is an example of including additional configurations for your addon.
3. You have access to nearly everything from within your addons. You can call them into your function with `global $nameOfResource`. The ones you most likely will need are below:
	* `$socket` - This is the raw socket, in case you need to interact with the server directly
	* `$config` - This is an array of all of the configuration options loaded for the bot
	* `$dbconnection` - This is the connection handle to the database.
	* `$ircdata` - This is passed into your addon by default. It is an array of all the various pieces of data. Most, but not all, are below:
		* `$ircdata['fullmessage']` - This is the full message that was seen
		* `$ircdata['commandargs']` - These are the arguments sent when a user has ran a command (e.g. `user` in `!seen user`).
		* `$ircdata['location']` - This is where the message was seen, either the channel or a PM.
		* `$ircdata['user_nickname']` - The nick of the user that sent the message
		* `$ircdata['user_hostname']` - The hostname of the user that send the message
	* `$timestamp` - The global timestamp value, calculated every time a message is received

	
# Miscellaneous
The main IRC channel where the bot is developed can be found in #cIRCuitbot on [Libera.Chat](https://libera.chat). 