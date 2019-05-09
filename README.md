# tool_corruptpdfdetector

A badly converted pdf detector, custom made for corrupt pdf which causes corrupt pdf exception on grading screen.

This tool finds out which assignment has badly converted pdf file.

# How it works

It scans assignment submissions in certain time frame. It will log the details of the assignment.

# Installation

Install the plugin the same as any standard moodle plugin:

https://docs.moodle.org/en/Installing_plugins

OR you can use git to clone it into your source:

```bash
git clone git@git.catalyst-au.net:elearning/moodle-tool-corrupt-pdf-detector.git
```

# Usage

You can view the detected assignment submission via the UI at,

`Site administration > Server > Corrupt pdf assignment finder`