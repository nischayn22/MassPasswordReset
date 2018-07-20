# MassPasswordReset
A MediaWiki extension to Bulk Reset passwords for Users on MediaWiki from a CSV file

# Installation

	Download this repo on your extensions folder
	Add the following on your LocalSettings.php: wfLoadExtension( 'MassPasswordReset' );
    Run the following command on your main directory: "composer require ircmaxell/random-lib"

# Usage
Open the Special Page "Special:MassPasswordReset" - only available for users with sysop permissions
Upload a CSV file containing usernames

# Credits
Created by Wikiworks.com for Santa Fe Institute
