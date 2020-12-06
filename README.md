# fpp-sms-control-too
This is a Falcon Player (fpp) plugin that allows you to control it using SMS text messages. It allows you to map text commands to FPP commands.

## Installation
In fpp, go to Content Setup, Plugin Manager and paste the following URL in the box and click "Retrieve Plugin Info":
`https://raw.githubusercontent.com/joeharrison714/fpp-sms-control-too/master/pluginInfo.json`


## How to set up:
- Configure voip.ms account
  - Note regarding costs: As of this writing, the cost for a standard U.S. phone number is $0.85/month and the cost per SMS message is $0.0075.
  - Create a <a href="https://voip.ms/en/invite/MTM1ODE5">voip.ms</a> account (This is a referral link. If you use it we both get $10!)
  - Order a DID number (aka phone number)
  - For routing, you can choose "System", "Hang up"
  - Edit the DID and ensure SMS messages are enabled
  - Enable the Rest API, create an API password, and authorize your IP address (The external IP where your fpp will be making requests from)
- Configure FPP
  - Configure the plugin
  - Enter your voip.ms username, API password, and your DID number that you created in step 1
  - Fill out the message text you want to be sent back to the sender
  - Click "Add" to add a message to listen for
     - "SMS Message" - the text of the message that will be checked for.
     - "FPPD Status Condition" - Only execute the command if FPP is in this status when the message is received.
     - "Playlist Condition" - Only execute the command if FPP is playing this playlist when the message is received.
     - Command - The Command to execute of the conditions are met.
NOTE: There can be multiple lines with the same message and all commands will be executed that meet the conditions.

### Notes
- Check the fpp-sms-control-too.log file in file manager for troubleshooting
- All received messages will be stored in fpp-sms-control-too-messages.csv file in the logs directory

## Commands
This plugin also adds a "Send SMS Message" fpp command that you can use throughout the system.