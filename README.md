# Funded Grant Database - REDCap External Module

## About

The Funded Grant Database is designed to facilitate the collection and management of information about funded grants at an institution. It also allows the administrator of the database to control access to the grant materials and track who has viewed/downloaded which grants.

The interface provides robust searching/filtering.

The module additionally requires two REDCap projects to support it. The data dictionaries [here](https://github.com/AndrewPoppe/funded_grant_database/tree/main/data_dictionary) can be used to create those projects. The project IDs for those projects (as well as several aesthetic options) should be configured in the external modules section of the control center.

This is a system external module, but enabling the module in a project will add a link to the database in the project's left-side menu.

## Installation

Releases from this repo can be downloaded, extracted, and moved into the modules directory of your REDCap server. You may also download and install this module from the REDCap Repo.

## Usage

1. Access to the database can be granted using the *users* project 
1. Grants can be added using the *grants* project
1. The database will be accessible at ***\<your redcap url\>*/ExternalModules/?prefix=funded_grant_database&page=src/grants**
1. Use REDCap's built-in url shortener to give this path a more friendly URL if you like

## Configuration

All configuration occurs in the system settings (Control Center -> External Modules). 

A complete listing of the configuration options:

* **Grants Project**: The PID of the grants project (see about section above)
* **Users Project**: same
* **Contact Person**: Name of the person who should be contacted by users who want access to the database and/or have questions about it
* **Contact Person's Email Address**
* **Email Users Upon Download**: Whether or not to send an email to users when they download grant materials from the database. Emails are set to send once per day at 6:00 PM. If you enable this, these settings are revealed:
    * **Email address** that these emails should come from
    * **Email subject** defaults to *Funded Grant Database Document Downloads*
    * **Email body** This is the text that will appear in the body of the email. Default body text appears below. The following keywords can be supplied in your text and will be replaced in the email message with the respective value (note that the square brackets *should be included*):
        * `[download-table]`: A table with each download the user made in the previous day
        * `[first-name]`: First name of the user
        * `[last-name]`: Last name of the user
        * `[full-name]`: Full name of the user
        * `[database-title]`: Name of the database. (system setting described below)
        * `[contact-name]`: Contact Person, described above
        * `[contact-email]`: Contact Person's Email, described above
* **Accent Color**: Main accent color (These and other aesthetic settings have decidedly Yale-centric default values)
* **Accent Text Color**: Text color used when appearing on top of Accent Color
* **Secondary Accent Color**: Used in highlighting certain table features, etc.
* **Secondary Text Color**: Text color used when appearing on top of Secondary Accent Color
* **Logo File**: Logo to appear on every page of the database
* **Favicon File**: Appears in tab title
* **Database Title**: The title of the database, used throughout the EM. Default is: *Yale University Funded Grant Database*

### Default Email Body Text

>Hello \[full-name\],  
>
>This message is a notification that you have downloaded grant documents from the following grants using the **\[database-title\]**:  
>
>\[download-table\]  
>
>Questions? Contact \[contact-name\] ([\[contact-email\]]("mailto:\[contact-email\]"))

## Attribution

This EM is based on a plugin originally created at Vanderbilt by Scott Pearson, Jon Scherdin, and Rebecca Helton (email: datacore at vumc.org). 