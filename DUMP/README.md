```
    TODO:
    - add option to fetch & run Adminer
    - add option to display link to adminer-auto-auth, or direct link to the lib, omitting all the plugins, dump conf layer, redirect etc... it can cause errors sometimes, not always needed
    -- (moze na dblclick to zrobic? pobierac z attr data linka?)
    - add option to autodestroy - delete dump dir contents (if no sql/zip/etc found there?) or only force on htaccess. better the option to delete completely)
    - display message when dump is not secured (try to omit if it detects the word "dev|stage" in instance constants)
    - add option to generate htauth+user
    - links to be / fe somewhere
    - automatic deploy of dump in a subdir - clone git, (set some settings?), lock with auth
    - to probowac na problem z komunikatem o hasle:
    tail -n +2 build-stage-v01.sql >  build-stage-v01fixed.sql
    strip first error line in dump
    // to sie pojawia w polowie pliku, jesli uzywamy ominiecia danych z niektorych tabel - bo to sa 2 dumpy sklejone w 1 plik
    // trzeba wiec najpierw obsluzyc sytuacje i uciac tym tailem, dopiero wtedy skleic oba dumpy w jeden
    // todo: find out how to checkout only specific subdir
    git clone git://github.com/w010/DUMP.git
```



# DUMP
## Damn Useful Magic Program for TYPO3

https://www.youtube.com/watch?v=14-fEwga82E


DUMP/BACKUP/MIGRATION TOOL FOR TYPO3
wolo.pl '.' studio 2013-2022
Supported TYPO3 versions: 4, 6, 7, 8, 9, 10, 11
(Note, that recently I don't test it in older TYPO3, so if it doesn't work try older DUMP version)

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
