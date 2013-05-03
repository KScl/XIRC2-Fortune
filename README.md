XIRC2-Fortune
=============

Fortuna's source (badniknet/#fortune)

You need the XIRC2 base (STJrInuyasha/XIRC2) to use this.

Setup:
* Place these files in \bots\fortune\.
* Under the `[Bots]` subheader in the configuration file, add: `load[] = "fortune"`
* Create a `[Fortune]` subheader in the configuration file for this module's configuration.

Options:
* `freewheellayout`: What layout to use for the "free" wheel; i.e. the one used for spins when no game is running. 
* `freewheelround`: What round the "free" wheel will be based off of.
* `superops`: An array of nicknames that will always be allowed to manage the bot regardless of channel operator status.
