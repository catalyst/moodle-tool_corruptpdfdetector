<a href="https://github.com/catalyst/moodle-tool_corruptpdfdetector/actions?query=branch%3AMOODLE_405_STABLE">
<img src="https://github.com/catalyst/moodle-tool_corruptpdfdetector/workflows/ci/badge.svg">
</a>

# Corrupt pdf assignment finder

A badly converted PDF detector, custom made for corrupt pdf which causes corrupt pdf exception on grading screen.

This tool finds out which assignment has a badly converted PDF file.

# Versions and branches

| Moodle Version   | Branch            |
|------------------|-------------------|
| Moodle 4.5+      | MOODLE_405_STABLE |


# How it works

It scans assignment submissions in certain time frame. It will log the details of the assignment.

## Installing via uploaded ZIP file ##

1. Log in to your Moodle site as an admin and go to _Site administration >
   Plugins > Install plugins_.
2. Upload the ZIP file with the plugin code. You should only be prompted to add
   extra details if your plugin type is not automatically detected.
3. Check the plugin validation report and finish the installation.

## Installing manually ##

The plugin can be also installed by putting the contents of this directory to

    {your/moodle/dirroot}/admin/tool/corruptpdfdetector

Afterwards, log in to your Moodle site as an admin and go to _Site administration >
Notifications_ to complete the installation.

Alternatively, you can run

    $ php admin/cli/upgrade.php

to complete the installation from the command line.

# Usage

You can view the detected assignment submission via the UI at,

`Site administration > Server > Corrupt pdf assignment finder`

# Crafted by Catalyst IT

This plugin was developed by Catalyst IT Australia:

https://www.catalyst-au.net/


# Contributing and Support

Issues, and pull requests using github are welcome and encouraged!

https://github.com/catalyst/moodle-tool_corruptpdfdetector/issues

If you would like commercial support or would like to sponsor additional improvements
to this plugin please contact us:

https://www.catalyst-au.net/contact-us