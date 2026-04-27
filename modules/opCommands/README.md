# opCommands

> **Deprecated.** This module has been superseded by `channelModeration`, which includes all op/voice commands plus kick, ban, kickban, redirect, and DB-backed timed actions with automatic expiry. New installations should use `channelModeration` instead. Do not load both modules at the same time — the command names overlap.

This module enables admin users (users with `bot_flags` set to `A` or `O` flags in the `known_users` table) to run various op commands.

## Installation

Copy the root `opCommands` folder into the `modules` directory where you installed the main bot. Add `opCommands` to the modules section of the bot config file.