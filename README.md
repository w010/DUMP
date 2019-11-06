# DUMP
## Damn Usable Management Program for TYPO3

https://www.youtube.com/watch?v=14-fEwga82E


WTP DUMP/BACKUP TOOL FOR TYPO3 - wolo.pl '.' studio  
2013-2019  
Supported TYPO3 versions: 4, 6, 7, 8, 9  

! you should change default password !

###							WARNING!   CRITICAL

THIS SCRIPT IS FOR PRIVATE DEV USE ONLY, DO NOT PUT IT ON PUBLIC UNSECURED!  
IT DOES LOW LEVEL DB / FILESYSTEM OPERATIONS AND DOESN'T HAVE ANY USER-INPUT SECURITY CHECK.  
IF THIS IS YOUR SITE AND IT RUNS IN PUBLIC/PRODUCTION ENVIRONMENT AND YOU ARE  
NOT SURE IF THIS FILE SHOULD BE HERE, PLEASE DELETE THIS SCRIPT IMMEDIATELY  

Please remember, this is my script for my use and I'm giving it to you that you could save some
time at work on repetitive system operations. There is no guarantee that it fully
works in all environments and situations like you want it to - sorry.



### HOW TO USE

This utility is designed and tested to run beside your project in your ```[webroot]/DUMP/``` directory.

All database and filesystem packed dumps also are exported there and are read 
from this location on import and to be displayed in selectors.



### Compatibility

Although I said it supports 4.x versions branch, please note that I don't test it in such anymore.
So it might happen that it just doesn't work there, especially on older php like 5.3.
In such case try to use older version from git history. 
