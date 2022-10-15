# doMessageFromCommand

This module will respond to a command and reply with an ACTION (/me).

## Installation

Copy the root `doMessageFromCommand` folder into the `modules` directory where you installed the main bot. Add `doMessageFromCommand` to the modules section of the bot config file. 

## Customization

In order to set the command and reply message you want the bot to use, you must modify `module.conf` and set both the command (defaults to `command`) and the action. 

In the `module.conf` file, edit the first line, that starts with `module[]=` and change `command` to the command you wish to use this module.

Modify the text in the `action` variable to customize what you want the bot to reply with as its action. This also supports a the following variables:

1. `%COMMANDARGS%` - Will use the command arguments as the action text, e.g. if the command was `!command dances around` the both would reply with the equivalent of `/me dances around`. If this is blank (user gave the command with no arguments, e.g. `!command`) the bot will not respond.
2. `%USERNICKNAME%` = Will use the nickname of the user that issued the command as the action text