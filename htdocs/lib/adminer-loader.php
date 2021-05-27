﻿<?php
/**
 * DUMP add-on
 * Adminer loader
 * 
 * Includes Adminer + it's plugins and customizations, but first does some magic tricks to pass database config
 * and to make autologin possible.
 * 
 * 
 * WTP DUMP/BACKUP TOOL FOR TYPO3 - wolo.pl '.' studio
 * 2013-2021
 */


file_disposition();


// use DUMP's final db credentials
global $databaseConfiguration;

// do we need this configured
$ADM_driver = 'server';
// set some more settings needed by Adminer
// tbd: do we need to have these configurable also for standard dump operations? then move it there
$databaseConfiguration['driver'] = 'mysqli';
$databaseConfiguration['port'] = '3306';

// shorthand
$dbConf = $databaseConfiguration;


// Autologin db config validate
if (!$dbConf['username'] || !$dbConf['host'] || !$dbConf['database'])   {
    print "ERROR - Adminer-loader: database credentials are incomplete!<br>Config:";
    var_dump($dbConf);
    exit;
}



// redirect with autologin params. exclude for file disposition - auth not needed for that, so don't complicate things
if (!$_GET['username']  &&  !$_GET['file']) {

    // Get signon uri for redirect
    $ADM_SignonURL = ($_SERVER['REQUEST_SCHEME'] ?: ($_SERVER['HTTPS'] ? 'https' : 'http')) . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    
    
    // Redirect to adminer (should use absolute URL here!), setting default database
    $autologinRedirectUri = $ADM_SignonURL . '?' 
        /*. 'lang=' . 'en' 
        . '&' */. 'db=' . rawurlencode($dbConf['database'])
        . '&' . rawurlencode($ADM_driver) . '=' . rawurlencode($dbConf['host']
            . ($dbConf['port'] ? ':' . $dbConf['port'] : '')
        )
        . '&' . 'username=' . rawurlencode($dbConf['username']);
    
    if ($ADM_driver !== 'server') {
        $autologinRedirectUri .= '&driver=' . rawurlencode($ADM_driver);
    }


    //print $autologinRedirectUri; die();


    // Build and set cache-header header
    /*foreach([
        'Expires' => 'Mon, 26 Jul 1997 05:00:00 GMT',
        'Pragma' => 'no-cache',
        'Cache-Control' => 'private',
        'Location' => $autologinRedirectUri
    ] as $header => $value)   {
        header("$header: $value");
    }
    print $autologinRedirectUri;
    exit (0);*/
}



function file_disposition()  {
    switch ($_GET['dump_disposition'])  {
        case 'adminer.css':
            header('Content-Type: text/css');
            print <<<EOT
'/* Merged and fixed version of Hever's and Frank Bueltge's skins by Oguz KONYA. I liked Bueltge's skin but I wanted the icons, too.
/* So I merged them into one file, fixed a couple of problems, added some paddings here and there, voila!

/* Redesigned (iconized) by Hever [hev.cz] - June 2009, ver 0.1.3 */
/** 
 * Alternative style for Adminer by Frank Bueltge
 * @link http://bueltge.de/
 */
 
/* Added icons */
/* IE doesn't support inline images - using some hack that eliminate IE*/
html/*\*/>/*/*/body .error {background:#FFEEEE url("data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAIsSURBVDjLpVNLSJQBEP7+h6uu62vLVAJDW1KQTMrINQ1vPQzq1GOpa9EppGOHLh0kCEKL7JBEhVCHihAsESyJiE4FWShGRmauu7KYiv6Pma+DGoFrBQ7MzGFmPr5vmDFIYj1mr1WYfrHPovA9VVOqbC7e/1rS9ZlrAVDYHig5WB0oPtBI0TNrUiC5yhP9jeF4X8NPcWfopoY48XT39PjjXeF0vWkZqOjd7LJYrmGasHPCCJbHwhS9/F8M4s8baid764Xi0Ilfp5voorpJfn2wwx/r3l77TwZUvR+qajXVn8PnvocYfXYH6k2ioOaCpaIdf11ivDcayyiMVudsOYqFb60gARJYHG9DbqQFmSVNjaO3K2NpAeK90ZCqtgcrjkP9aUCXp0moetDFEeRXnYCKXhm+uTW0CkBFu4JlxzZkFlbASz4CQGQVBFeEwZm8geyiMuRVntzsL3oXV+YMkvjRsydC1U+lhwZsWXgHb+oWVAEzIwvzyVlk5igsi7DymmHlHsFQR50rjl+981Jy1Fw6Gu0ObTtnU+cgs28AKgDiy+Awpj5OACBAhZ/qh2HOo6i+NeA73jUAML4/qWux8mt6NjW1w599CS9xb0mSEqQBEDAtwqALUmBaG5FV3oYPnTHMjAwetlWksyByaukxQg2wQ9FlccaK/OXA3/uAEUDp3rNIDQ1ctSk6kHh1/jRFoaL4M4snEMeD73gQx4M4PsT1IZ5AfYH68tZY7zv/ApRMY9mnuVMvAAAAAElFTkSuQmCC") no-repeat scroll 0.8em center; padding-left:38px;}
html/*\*/>/*/*/body .message, #menu p.message {background:#EEFFEE url("data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAKfSURBVDjLpZPrS1NhHMf9O3bOdmwDCWREIYKEUHsVJBI7mg3FvCxL09290jZj2EyLMnJexkgpLbPUanNOberU5taUMnHZUULMvelCtWF0sW/n7MVMEiN64AsPD8/n83uucQDi/id/DBT4Dolypw/qsz0pTMbj/WHpiDgsdSUyUmeiPt2+V7SrIM+bSss8ySGdR4abQQv6lrui6VxsRonrGCS9VEjSQ9E7CtiqdOZ4UuTqnBHO1X7YXl6Daa4yGq7vWO1D40wVDtj4kWQbn94myPGkCDPdSesczE2sCZShwl8CzcwZ6NiUs6n2nYX99T1cnKqA2EKui6+TwphA5k4yqMayopU5mANV3lNQTBdCMVUA9VQh3GuDMHiVcLCS3J4jSLhCGmKCjBEx0xlshjXYhApfMZRP5CyYD+UkG08+xt+4wLVQZA1tzxthm2tEfD3JxARH7QkbD1ZuozaggdZbxK5kAIsf5qGaKMTY2lAU/rH5HW3PLsEwUYy+YCcERmIjJpDcpzb6l7th9KtQ69fi09ePUej9l7cx2DJbD7UrG3r3afQHOyCo+V3QQzE35pvQvnAZukk5zL5qRL59jsKbPzdheXoBZc4saFhBS6AO7V4zqCpiawuptwQG+UAa7Ct3UT0hh9p9EnXT5Vh6t4C22QaUDh6HwnECOmcO7K+6kW49DKqS2DrEZCtfuI+9GrNHg4fMHVSO5kE7nAPVkAxKBxcOzsajpS4Yh4ohUPPWKTUh3PaQEptIOr6BiJjcZXCwktaAGfrRIpwblqOV3YKdhfXOIvBLeREWpnd8ynsaSJoyESFphwTtfjN6X1jRO2+FxWtCWksqBApeiFIR9K6fiTpPiigDoadqCEag5YUFKl6Yrciw0VOlhOivv/Ff8wtn0KzlebrUYwAAAABJRU5ErkJggg==") no-repeat scroll 0.8em center; padding-left:38px;}

html/*\*/>/*/*/body a[href$="&sql="] {background:url("data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAHGSURBVHjaxFPNLkNREP5aB6WhaKLSVtKFiIUqK1sLT2DhTXgDL2FlxSOw8FOLRiyQWhDRiKSEhKQJou49P9fMnFsLsevCJCcz594z3/m+mTmJKIrQjSXRpXUNoGqbm39qcMbAkbyIvbVwYQhD3tIeWsOQ1+QVH86Xyz+JXJPIOb9iAI4ZpOMd/yN/vb/vAdiC93cP0El2dNA6z4RjYyW2MaPU0BB0u+0BOGGsVML49LSA3J+cYDCblb0l6jeHBwg/26isrOB0a8uzYBlBgKShgD8M53J4aTTQqFaRSCYl+WxnG83zcxTmKpKg+vtFu9W8NDQDMCXW+VivYyAzjKmlJahUSpiYUKPVbCKTz0sCGzOyOpBLzdcXlCMdDDIxO4vboyoyhQIKlYocLi0uIjczIyBcA98dYqx9NywxUEyDi/P29IyF1VU5dFeroS+dRnF+Xvajk5N0ayjx8tq6+FuSyiwUF4LRHi/reLg4o9ijs6xG9RjWGowUi/h4fcXexoa0L4oc0mNZuVgFzID09VKBnFXoUb7Pnb5zQrvVovpkZC4QzwfiN6QM1eBqdxcmnq6IAA395Mlz8eTxTZwg/pcl/v01fgswAESqYZbsIsnLAAAAAElFTkSuQmCC") no-repeat scroll left bottom; padding-left:22px;}
html/*\*/>/*/*/body a[href*="dump="] {background:url("data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAJrSURBVHjajFPPaxNREP72V8ymTbGQbRB/IT0otdV6CcWTgqCoUFQQEXoSEQ/+AypK755E1KsULIpGgw1F9KC22lgstFgQLdoWhSab2PRHTNrs7nvObGhSsRa/ZXjv7c6b+eabWUVKiX9AJQuRRci2k+260N91VwgJIQSEV1mVnjf5axTjvCfFFn7hCcBlBzJNVRA0gAZTQ1ODgXC4gDtjV9AW2QNFUTA0/Q66kPLSqVg4shYF8vEdKQ7mln+i+/VVRM0oFpbmETLq/SS6J4R/OfHtHmV24XouHFo94REDAyE9hMZgBMkvT2GZFqxQE6by0/g4P74SoKLB2ZZzWA8ffqRQLBcxNTuFjRTwxpmbfad7Oo/rHhdN6B20/6JvaKyBCnODhqNbL+PRxHXUGXWINV5kl9TDrkQct/pnZKHkyPsDGcl4MJiWq5Ecsf84LxTL8nbyK2+Pke3XHWJAQkLXgMdDNjRNRTxlQ6UmakRD0vN8NEd7EBsFO6Impu1fzGCCrOSXwCro5HEiZiExnEVnrKlaypNhG4fba02aTC8ik1/ibZrM9RlIkkGnjH0jWWJQWRn8TpLI8fcZ6MSA1WrZFERx2eHPTEPqjksBqASDLh7ZZ+HlWA6H9tYyvhjNomNnI8Km7p8/f5+HGdB46/lJeOoYPHV8mbGy8gA59HngU74asNkKYKVzlQCuW9GA6B5si+DVeA4HWmsM4qksTnZY1TMz0NRauysaUAmt2+oxM1tCM6k8mS74WXjIdm8O+JdWg36oKgXqgnzb3TvRXvZEneN6YPNIFw7MY70W5haXnlUHbp3f+b/wW4ABAAtWTLcKdqLcAAAAAElFTkSuQmCC") no-repeat scroll 2px bottom; padding-left:22px;}
html/*\*/>/*/*/body a[href$="dump="] {background:url("data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAHzSURBVHjajJPPS+NQEMcnaaxpdWsp6Q8vtWtdkIo9+B948SjIgruCUg8F/wGP/glePe5JcBehwl4qePGm0J6KQj30UBHEzaFLUromTfPizJMnrxhhB4bJvDfzme97SZQgCOB/be98J2AsAMYYMP81KjKg0WhMYljHtS30RSz4gjkGdot559F7OLt8vvi1bJRBURS4vr8CTTQ3m80SFv1MpVJlwzBA13XuNGUwGKyY1p+Vk9aPzWw8C7ZjQXximu+9ATA5KhQK5Uwmw+nCIpEIJBIJOLjah3Q8w7379x5urFsOUEUhTl/C6R+ef35mARzPhW6vC+q/iHvyvaYwXwIQje6j1+tBv98H13VhNBpxxyPA5tw2TI50MPQsrMbXbOo53fmtaJIC7iSZAMPhkOe+73MA5Ruz3yCfz9NlvykLBUSjUR41TQPP8zhE7JNSyj8E0CbJpkiXKRoIRFABeQcQd0DFwlVV5evUTFBZRSiATEwnp4lCAa0LI2AoQC4WimRl4qgyYOw12rYNyWSSX6L4mKiBnukDK5VKYJrmmBpZwW6r1TotFotTuVyOQwQgnU7zV9npdKDdbj+hgnXRN/Yz1ev1zwg6QP+KU2disRiXblkWOI7zhJOPce+wWq2aoQDZarXaJ2xYQGfod5VKxQ2rexFgAI4OiAKxKkWeAAAAAElFTkSuQmCC") no-repeat scroll 2px bottom; padding-left:22px;}

html/*\*/>/*/*/body select[name="db"] {background:white url("data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAQAAAC1+jfqAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAEYSURBVBgZBcHPio5hGAfg6/2+R980k6wmJgsJ5U/ZOAqbSc2GnXOwUg7BESgLUeIQ1GSjLFnMwsKGGg1qxJRmPM97/1zXFAAAAEADdlfZzr26miup2svnelq7d2aYgt3rebl585wN6+K3I1/9fJe7O/uIePP2SypJkiRJ0vMhr55FLCA3zgIAOK9uQ4MS361ZOSX+OrTvkgINSjS/HIvhjxNNFGgQsbSmabohKDNoUGLohsls6BaiQIMSs2FYmnXdUsygQYmumy3Nhi6igwalDEOJEjPKP7CA2aFNK8Bkyy3fdNCg7r9/fW3jgpVJbDmy5+PB2IYp4MXFelQ7izPrhkPHB+P5/PjhD5gCgCenx+VR/dODEwD+A3T7nqbxwf1HAAAAAElFTkSuQmCC") no-repeat scroll left bottom; padding-left:16px;}
html/*\*/>/*/*/body select[name="db"] option {padding-left:18px;}

html/*\*/>/*/*/body #menu p a[href*="&select="] {background:url("data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAHISURBVDjLpVPNK0RRFP+9D98syMwUspHkm9I0YkFZWBFKkZ0s7a3Ewh+ilChK7FgoZCJFKYlYKB8zk2+Z5t0P577He29kQU7dd+6575zf+d1zztWklPiPmOozt/U4SThjXIoyIQS4AJjSXO0lGGlvcXAm6Vzsz4xUhm0AIeX4QLig+C+ZpxbOG1wGhGYHr1zMUmZGWRgs0ha3PE1nX/8mWmdgWTzLB+DUYbhm9FfZ35IEyrhXA3VXJfPbsV8B9LQUIeUHYJ8ASobag1jcucNgW8g9W4reYSDi2YnnZDoDiwCokDANct6NwTB0LEdj0HRA/wxa2SN25JNBEdWluUhZ366gqmAaGvrCAXKOozccTGPgt8+vn8GYSGcgyTYp3dpBnBg42nbQPRBTo5bTvqYkmxL6AQhNTWQGBXY3B7BxlEBXozcW64dxRKoKUZBju+P06gl5WaaviMJBM3TNDlbypemIZgHYOnlwASsCmW7nHADGnBoQ3c76YmweJ9BR5zFYjsbRHwm4tmJg6PhWA7pCXXk+bu7fURHKweXtq/sWaksz7SC/CCGFrwtyZ3r+rCnFRZ7qr1qc6mLZj4f9OEyPL8lVpbX/PucPv5QPKHB1TdEAAAAASUVORK5CYII=") no-repeat scroll left bottom; clear:left; display:block; float:left; height:16px; margin-right:8px; padding-top:1px; overflow:hidden; padding-left:16px; width:0; text-decoration:none;}

html/*\*/>/*/*/body #menu p a[href*="&table="], html/*\*/>/*/*/body #menu p a[href*="&view="] {clear:right; margin-left:24px; display:block; height:17px; padding-bottom:1px; text-decoration:none;}

html/*\*/>/*/*/body #menu p#tables br {display:none;}

html/*\*/>/*/*/body a[href*="&create="] {background:url("data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAJ6SURBVDjLpZNZSNRRGMV//2XGsjFrMg2z0so2K21xIFpepYUiAsGIICLffI8eWiBBeg3qQV+KwBYKLB8qpHUmrahcKLc0QsxldNSxdPz/79LD1ChBUXTh8sG93POdc75zDa01/7NsgGvPR09rzQmpVZZSCqlAKIWUCqk0QqoZWyKFRir1uvxIbsAGUFqXHQqkpP1L57M3Pm5MMJBKpQHUdF9BKIGQAlcJXOlOVykSdye3leO6MmkGQNyHw+uO/1X3bzGBK+S0B1IqAKqDg3986HeCZPffwvJtoNT7lOZLvUdtAPEDAKBkRzo3QwMUb89InN1uGGD3spdE214xe8MRUnM2MfppNW0Pqy7YAK5UKK2xLbhdP4hlmdxpGMQwwQT8ziNiI534c7cT6WrFazikzF2Eb8HS1IQEDdiWwcHAQmpehTkQSAcgNvSMiYFW5uUUMdV3HW+ywefGNqITJsbUUL75k4FWYJtQ+yaMZcXrk1ANk/33mbdiD7EvlRieETy+FJLkMFcjRRSW3emIAwiF1hqPBfu2LGSWbbA1uZ41SfWkrtxPrPcypsfFiWYzFGzGKTjFV28WEJeIUHETLdOgrmkI1VdHpCdEet5enP4qLK9mKrqMgedv6cyrAP+qxOTiUxAi7oEJi8frELoFoTLpa7nI/HQvscgSRt+0kV1SSW7qYtp7xrBMphm4Mi5h/VIfTcEq1u0oJaknSEdNiMYHET7UvcMpPEN31Ed7zxgASmk1I0g6dK66s8CRak5mVxjnfS05+TsZCw/T9baTx1nnGb47DrQksjE6HrsHYPz6nYt3+Sc3L8+wA2tz0J6pF5OD4WP7Kpq7f5fO79DfSxjdtCtDAAAAAElFTkSuQmCC") no-repeat scroll 2px bottom; padding-left:22px;}
html/*\*/>/*/*/body a[href$="&create="] {background:url("data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAIpSURBVDjLpZNPSFRRFMZ/749/Kt3IqFTSRoSMmrGIYTTbpEJtjBCCok1Em9JVG1dRC8FFEES5aGFEgRRZWq1iLKKxBiNqLDcltQgmHR9hY6LOu+feFm+YGVsZXbh8nHO53/nud8+xjDH8z3IB7r5avGgMZ8XoBq01okFpjYhGtEGJLtmCKINo/XbgVFPUBdDG9PVEq0P/UvnSvdlwQYFoHQIY/3obpRVKFL5W+OIXUVThrL91AN+XihKCwIeTu85sqPryqsJXUvRARAMwkshsiKB7fw25UgKVJwA40V7H/cl5jh+oL+RGk/P0xIqxl11dr8AXjTYG14HRNxkcx+ZhMoNlg52/ND6VAWMoc6F5+2Zy/l9PMIDrWByL1jI+tcDRaN06BaXxbDqLUnq9AqPBteHpuwUcJ0AIcgBXH93h+/wEyyuLrPk5cmv7gNY8gdIYYyhz4PDeWuIpj85IsS2ujQ2zJAk6DkZpqGnixcwYyU+PifUOX7Eh6DoAx7aIpzwA4imPeMrj+bTH+88PaNkZQWwhsrULsXxie9oAzgcESgUe2NAZCeE6AXZGQhwKh/Cyc5RZVXQ39wFwoeMmjXVhgMqiB8awe0cVP36u0Fi/iW9zvwuzkF3+xUz6Nal0gv6uWww+O02lUwGwmv8FM3l55EtLTvQWXwm+EkRpfNEoUZRXHCE5PUFbuJ0nH4cot1wSH14C3LA2Os6x3m2DwDmgGlgChpLX0/1/AIu8MA7WsWBMAAAAAElFTkSuQmCC") no-repeat scroll left bottom; padding-left:22px;}

html/*\*/>/*/*/body #content p a {padding-left:2px;}
html/*\*/>/*/*/body #content p a[href*="&create="] {padding-left:22px;}
html/*\*/>/*/*/body #content p a[href*="&select="] {background:url("data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAHISURBVDjLpVPNK0RRFP+9D98syMwUspHkm9I0YkFZWBFKkZ0s7a3Ewh+ilChK7FgoZCJFKYlYKB8zk2+Z5t0P577He29kQU7dd+6575zf+d1zztWklPiPmOozt/U4SThjXIoyIQS4AJjSXO0lGGlvcXAm6Vzsz4xUhm0AIeX4QLig+C+ZpxbOG1wGhGYHr1zMUmZGWRgs0ha3PE1nX/8mWmdgWTzLB+DUYbhm9FfZ35IEyrhXA3VXJfPbsV8B9LQUIeUHYJ8ASobag1jcucNgW8g9W4reYSDi2YnnZDoDiwCokDANct6NwTB0LEdj0HRA/wxa2SN25JNBEdWluUhZ366gqmAaGvrCAXKOozccTGPgt8+vn8GYSGcgyTYp3dpBnBg42nbQPRBTo5bTvqYkmxL6AQhNTWQGBXY3B7BxlEBXozcW64dxRKoKUZBju+P06gl5WaaviMJBM3TNDlbypemIZgHYOnlwASsCmW7nHADGnBoQ3c76YmweJ9BR5zFYjsbRHwm4tmJg6PhWA7pCXXk+bu7fURHKweXtq/sWaksz7SC/CCGFrwtyZ3r+rCnFRZ7qr1qc6mLZj4f9OEyPL8lVpbX/PucPv5QPKHB1TdEAAAAASUVORK5CYII=") no-repeat scroll 2px bottom; padding-left:22px;}
html/*\*/>/*/*/body #content p a[href*="&page="] {background-image:none; padding-left:0;}
html/*\*/>/*/*/body #content p a[href$="?database="] {background:url("data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAIkSURBVDjLpVNNiFJRFP7eU1E0KSLTMpAwYSxyaidDtChm0WYQ3NSutv2s2kwwm2igNgMtooUQEQhhA9GqhSDTQsZZFDbNDBgVg5bSw9J8rzFF33udc+HGg0ladOHj3nPe+b7zc99VbNvG/yy30yiVSl4SnCNcsixrivYEgY7WJu0faX9EKGUyGVNyFFkBkY/T+WkoFEpFIhEEAgH4/X7w916vB8Mw0Gg00G63y+S7mM1mm4LIAYxisbhSr9c5nT1pjUYju1qt2oVC4YnkqbIUMk6Ew+F/9hyNRkFJLuyaATmFoqZp8Pl88Hq98Hg8wtfv99HpdNBsNhGPx0XsRAG3241ut4vBYCDs8XgMXdcxHA7FN/b9VUD25HK5RAUczKC+hYgcNpNN05xcAQdLkqIoIlj6VFWdXIEUkAQGV8M2k2vaG3z6sYGfVR39XzsHlm/dX3h5d31xlwAHM5goBd5+LuO75z3OnU3jyP4EVrZeKGub2p309cP7VKcAQ2Znoiz3deMVTk1Nw1RNTB+ahamMkD45w7RrfwSYwFdFf6K4Quf6pmvwKHswl7wh7Jvnc4gfTPHR52zhcqVSeZZMJgOxWEyI8BC5CmOnh63WKtZbZczPPsa94hX4XCLJQHG+xnw+f5SEFghZmvhefgvcTqn2HN3gBmZSZ5CInMaHr1Wsvivjy3ZvSZn0nHO5XJDIxwgWDbW2vL10m9xXCUGCQXi49qA1/xvyq6BCh7yZeQAAAABJRU5ErkJggg==") no-repeat scroll 2px bottom; padding-left:22px;}
html/*\*/>/*/*/body #content p a[href*="&edit="] {background:url("data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAJvSURBVDjLpZPrS5NhGIf9W7YvBYOkhlkoqCklWChv2WyKik7blnNris72bi6dus0DLZ0TDxW1odtopDs4D8MDZuLU0kXq61CijSIIasOvv94VTUfLiB74fXngup7nvrnvJABJ/5PfLnTTdcwOj4RsdYmo5glBWP6iOtzwvIKSWstI0Wgx80SBblpKtE9KQs/We7EaWoT/8wbWP61gMmCH0lMDvokT4j25TiQU/ITFkek9Ow6+7WH2gwsmahCPdwyw75uw9HEO2gUZSkfyI9zBPCJOoJ2SMmg46N61YO/rNoa39Xi41oFuXysMfh36/Fp0b7bAfWAH6RGi0HglWNCbzYgJaFjRv6zGuy+b9It96N3SQvNKiV9HvSaDfFEIxXItnPs23BzJQd6DDEVM0OKsoVwBG/1VMzpXVWhbkUM2K4oJBDYuGmbKIJ0qxsAbHfRLzbjcnUbFBIpx/qH3vQv9b3U03IQ/HfFkERTzfFj8w8jSpR7GBE123uFEYAzaDRIqX/2JAtJbDat/COkd7CNBva2cMvq0MGxp0PRSCPF8BXjWG3FgNHc9XPT71Ojy3sMFdfJRCeKxEsVtKwFHwALZfCUk3tIfNR8XiJwc1LmL4dg141JPKtj3WUdNFJqLGFVPC4OkR4BxajTWsChY64wmCnMxsWPCHcutKBxMVp5mxA1S+aMComToaqTRUQknLTH62kHOVEE+VQnjahscNCy0cMBWsSI0TCQcZc5ALkEYckL5A5noWSBhfm2AecMAjbcRWV0pUTh0HE64TNf0mczcnnQyu/MilaFJCae1nw2fbz1DnVOxyGTlKeZft/Ff8x1BRssfACjTwQAAAABJRU5ErkJggg==") no-repeat scroll 2px bottom; padding-left:22px;}
html/*\*/>/*/*/body #content p a[href*="&table="] {background:url("data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAJcSURBVDjLpZPtT5JhFMafrW997I9rscA+FFu2QRurtlw5cQ4InLpwBogIPNFSiNJ4C+JVkj0QTBHQKFPQlJfwlanY1tXz3ARkn2jd27Wz++yc33XOvd0UAOp/RNGR/X5zeH9rOlTDVKAK3fsqJrxlqN27GHPuYHh+G4rXRQzZNjEws47Hli/oo/PxNsAU3qvWT3/gX3TPuHrWBhiC30nSktXDtKLB1NI4NKkxqBMqjDByPFkcxNBCPwbCfXgUeEBq705m0AZM+qsk2e3hau88W+4ANOy+XPLFQrkrcbW31KkOYJx9rBaAOzPR0gVHW6x593q9cDgcqB6e4sZoogMYdXzD0ck5ZhfLsHGKVfAqVoadKcMdzcLr82PuwwZCoRACgQCWVzdhoK2gaVpDAMNzWzhkAXamQpze/I4t13w+j2AwiFwuh7W1NXg8HmQyGSgUCshkssuU3F7AQf0c84kK3n68KFc4hXQ6DavVCqlUCqVSSdaIx+NQq9UGMsHg7Ab2jxtwp5rOvqUqia3CUqnEObWn0mp1KBaLcLlckMvloPpfrhOAl230/SGLxQK3241CoQC9Xg9nskKk1emQzWZZkBZCoRBU3/NP2GMBgXTTObjSjI1GA8lkEgzDwO/3E4iObXY6nYhEIhCJRHoWcIW6b1pF7egMlYNT7NROUKzU8XX3GJ+3D2E0GgmAm4Zbh2s0mUyIRqMcAGKx+BIlMeSiYu1K/fbEMm4+TaFnJIHrSgZX5TFIZNPo7e1Fj9QOs9kMlUqFaw9pCASCnzwe7x15xG6/rUQiAZ/Px9/5XyhZOMVGKlOdAAAAAElFTkSuQmCC") no-repeat scroll 2px bottom; padding-left:22px;}

html/*\*/>/*/*/body #content a[href*="&database="] {background:url("data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAKRSURBVDjLhVNLTBNRFD3TTju1FCcBaxuaQEr94ydiZIHGpcFISBOi0YREZWHCVoyRxKUxxq0LXcACQyLsjO6KjSEiDfHDz0S0CLSxlFKd2g7MTDudGd+bMAQCxJucuXfuu+fcO/PeYwzDALVoNMqRuI3guq7rR4g/SEBC/Svxc8T3EUTD4bCGTcZQAUI+RvxLr9d70u/3o6KiAm63G3Qtn89DFEUkk0lks9lRkrvW3t6e2lCgRZFI5F0ikaDtjN1MVVVjYmLCGBoa6qccC7Z1kQafz4f/WSAQAGlyaXOOpQ+SNNUymQxcLhc4joPD4TBzkiRBEASkUimEQiGzdlcBlmWRy+WgKIr5Xi6XUSgUUCwWzTVN+IAzeOOde71orP0eAaOkbrDWf6Cw2+3mBLSYgny3KULXPOUY2BUB/hMd4IOn8XfhMGYjvU+2TECLLRLDMNA0zYw5JYa6Ghke/hyEn9/gZEqo3OuHp7qW3yJgESjoNPSdlb8gWCOCr29BMT0Ip5tBYnIWqlL6o8irzVsEaHcKSqQCen4cweok+FAblNRz2JxlODx1cEkzGWmVbTl7Z/jHhgCF1Z3GYjIKf+U8+ANhQn4Gm6OMUiGI9MhHg5Gl1sbu8UnKNc8B7Ui3ipxEcwvlpVFw6hz2N1xGabkXdqeBYqEOmfefEZWac4e6xz9Z22hbn+BmLBZbi8fjEBdG4NF/QdUDSM88hQ4FawKJR6cxLDZl86qzZdtdoDYwMBAkQg/2LL/ovNLVh++Dd7G0OAau9hTkrKgnnE39GW3f/Z6enpUdBSx7ePu4eq+zi4VNw+TbV0gsxFd5b9X5i4+mpnY63tsErl6okhvrfWzT0SAMR3FMXsnean08Pb/b/fgHqpjCspi90kkAAAAASUVORK5CYII=") no-repeat scroll 2px bottom; padding-left:22px;}
html/*\*/>/*/*/body #content p a[href*="&schema="] {background:url("data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAFOSURBVDjLtVK7SgNRED0b9iuM2lr4QK1DQIyk0FZsJAj+gH+ilRZb2NjaRHTLmA9QFKz9huzm7t37Hu+u7IJgQjR6YLjDzOXMmcMERIR5EE5qXA4z4sqACYWEC5wfLQXf/WtMIuDSoL0A7DZDjBj/uYI0l8jzEEJYJMkvCEZM4PqZIxlzpGk+kSCY18TGtGYcx9Tv96dOqBUMBgNyzsFaC621312Ac+59yJFlGRhj5VvVoigKvniglEK32w1mkd3r9ejPPAjOhqdknYX18p1/rzo3pYqTh0OSRkJI5UMgPn4s61sX66SkhtEGcISGsQad5gH2FvehfV5BaIF2cwet5RZyKeu68pe5ubKG7dUNP5AQGltMN57Mosgr5EIiVQmYGvtc1PVicqHY+dXpk8Dg7v22XKFo1ARe9v1bDOlXKKKCs4Sn1xdU1v3vIc2CD3bN4xJjfJWvAAAAAElFTkSuQmCC") no-repeat scroll 2px bottom; padding-left:22px;}

html/*\*/>/*/*/body #content p a[href*="&sql="] {background:url("data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAJ5SURBVHjapFNLaxNRFP4mnZmQZtKYpJ2I8ZE0NSbSWKpgBYNUhIJQUDddCaILwb34C0RcCi60vpa14sZFoSjxUWxTFEubFkxf9kFS+7Jp0yQmM5mH905SF9pSwQt3vnPnnvPNd86cA13Xsds+23XKsdMdQx//s0z/6hi54he3e8/0d3ZuK0FTFGhUJkVVhSbLUAiq5IxSCQrBEkGWOu8Lh38HGrlpWnlXCKhNSbZQo3cEE9FomYAuKZstE2wFa8RR1cpKqK2o4JhxmLVJVAlh5Df6IFj5MgENcHq9EAMBg2RucBDVLpdxVon0iXdvIf8sQHRIsNReht3XjI3ZI8iuPAOrkDyorBq3G6vT08ikUuAsFiN46HkXXL56eI41QV1/BMF+GulvCfCMDFvNXtSIHrC0KDTPhXgc7lAQdQ2thk2XIpewnkzCJvTA4T8PabEbfDWD+ZFxyEUdX94sw6QVCkZlxWAQU+8/YGZgALV+v0HgbWmBNzQMR0M7it8fg+HWwQk2cLkkcvJh5NNFsCVJMgq1ubiE5o4OI5CS8FYrya8b9saLKC48gIlTIG/6sBaLw3PpHrTEMrSXr4kPIaAKFkbjSA0PEVuFlB+DO8Ah2HoB8tITVPE6iplDWOn7jLn0CSQevoDV6TI+zEpUASkkZzaTYrLIrH3C/qMCFEbE4th9OEQexfQBrPYP44d+DuY91eBp+1dGgFVIDb729kKpdJfQtIy2yFNMdN/E5McYzAebUEhOYjZuQXaj5+9W/nOYbl9vLN26doOFScVI9BXmZ6dy9jpnpO1O5dfsRtBxxlk4Xu9mT4Z80DkpVlhZvdp+d3RmpyH7JcAAnHiAVYWMsdkAAAAASUVORK5CYII=") no-repeat scroll 2px bottom; padding-left:24px;}

html/*\*/>/*/*/body table tbody input[name*="check"] {display:block; float:left;}

html/*\*/>/*/*/body table a[href*="&edit="][href*="&where"] {background:url("data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAFUSURBVDjLrZM/SAJxGIZdWwuDlnCplkAEm1zkaIiGFFpyMIwGK5KGoK2lphDKkMDg3LLUSIJsSKhIi+684CokOtTiMizCGuzEU5K3vOEgKvtBDe/2Pc8H3x8NAM1fQlx4H9M3pcOWp6TXWmM8A7j0629v1nraiAVC0IrrwATKIgs5xyG5QiE+Z4iQdoeU2oAsnqCSO1NSTu+D9VhqRLD8nIB8F0Q2MgmJDyipCzjvYJkIfpN2UBLG8MpP4dxvQ3ZzGuyyBQ2H+AnOOCBd9aL6soh81A5hyYSGWyCFvxUcerqI4S+CvYVOFPMHxLAq8I3qdHVY5LbBhJzEsCrwutpRFBlUHy6wO2tEYtWAzLELPN2P03kjfj3luqDycV2F8AgefWbEnVqEHa2IznSD6BdsVDNStB0lfh0FPoQjdx8RrAqGzC0YprSgxzsUMOY2bf37N/6Ud1Vc9yYcH50CAAAAAElFTkSuQmCC") no-repeat scroll right bottom; padding-right:18px;}

html/*\*/>/*/*/body table input + a[href*="&edit="][href*="&where"] {width:0; float:left; display:block; height:16px; overflow:hidden; text-decoration:none; padding:0 0 0 18px; background-position:2px bottom; margin-left:5px;}
html/*\*/>/*/*/body table tbody td:first-child {white-space:normal;}
html/*\*/>/*/*/body table thead input {margin-right: 5px;}

html/*\*/>/*/*/body input[name="delete"], html/*\*/>/*/*/body input[name="drop"] {background:transparent url("data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAHSSURBVHjapFM5bsJQEB2zSIDFJrHYpEtyAyoKJAp6CrqIkBPkNDlBAKXjBEgUpKOBCyQNijFiEZvZl8z7wsjESYpkpNFfPO/Nmz9j6Xg80n/M9fWi3W7fMOnd4XAo8qogAbvO5xKvL6lU6s0aL1kVMDjP5ye/36+Gw2FyOp3EQFqtVtTr9WixWHT5/JhOp6s2ghP4ORaLyaFQiGazGa3Xa0HgdrvJ6/WSpmk0Go0MjnvIZDLVM0Gr1brm/WskEkkA3O/3abvdQjq5XC6xgoiVka7rNB6PNT6ns9nsu+OkpODxeBLBYJAGgwHt9/uzQ8Vms6Hdbie+KYqC+ASTFrARBMx2HwgEaDKZiHqn0yktl0uxtzrMMAyKx+MCc+4Cs13hwQCC1GQy+W3Lms2mUIUygbEqEBLNun8z8zswVgUfLO0WD4Z6kekn8/l8okNM8GFVUMYDoVWQ6HA4bEAzoyzL1O12kbRsJajwhYZhiUajJEnShWSAQaqqKnU6HahEGysXg9RoNPJ8+cwZZLSKp47m8/k5Kxzg4XBocNxDLper2ka5Xq+LUeatilahJLN1mEJ+ZDHKJthGAKvVauJnYi9ysHIqQee1xOsLg3/+mf5inwIMAJMhb74NwG5wAAAAAElFTkSuQmCC") no-repeat scroll left center; padding:1px 5px 1px 18px; border:0; cursor:pointer; font-size:.9em;}
html/*\*/>/*/*/body input[name="delete"]:hover, html/*\*/>/*/*/body input[name="drop"]:hover {color:red; background-image:url("data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAJdSURBVDjLpZP7S1NhGMf9W7YfogSJboSEUVCY8zJ31trcps6zTI9bLGJpjp1hmkGNxVz4Q6ildtXKXzJNbJRaRmrXoeWx8tJOTWptnrNryre5YCYuI3rh+8vL+/m8PA/PkwIg5X+y5mJWrxfOUBXm91QZM6UluUmthntHqplxUml2lciF6wrmdHriI0Wx3xw2hAediLwZRWRkCPzdDswaSvGqkGCfq8VEUsEyPF1O8Qu3O7A09RbRvjuIttsRbT6HHzebsDjcB4/JgFFlNv9MnkmsEszodIIY7Oaut2OJcSF68Qx8dgv8tmqEL1gQaaARtp5A+N4NzB0lMXxon/uxbI8gIYjB9HytGYuusfiPIQcN71kjgnW6VeFOkgh3XcHLvAwMSDPohOADdYQJdF1FtLMZPmslvhZJk2ahkgRvq4HHUoWHRDqTEDDl2mDkfheiDgt8pw340/EocuClCuFvboQzb0cwIZgki4KhzlaE6w0InipbVzBfqoK/qRH94i0rgokSFeO11iBkp8EdV8cfJo0yD75aE2ZNRvSJ0lZKcBXLaUYmQrCzDT6tDN5SyRqYlWeDLZAg0H4JQ+Jt6M3atNLE10VSwQsN4Z6r0CBwqzXesHmV+BeoyAUri8EyMfi2FowXS5dhd7doo2DVII0V5BAjigP89GEVAtda8b2ehodU4rNaAW+dGfzlFkyo89GTlcrHYCLpKD+V7yeeHNzLjkp24Uu1Ed6G8/F8qjqGRzlbl2H2dzjpMg1KdwsHxOlmJ7GTeZC/nesXbeZ6c9OYnuxUc3fmBuFft/Ff8xMd0s65SXIb/gAAAABJRU5ErkJggg==")}

.logout {font-size: 8pt !important;}
#logout{ height:17px; border: none; background: transparent url("data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAJHSURBVDjLlZPNi81hFMc/z7137p1mTCFvNZfGSzLIWNjZKRvFRoqNhRCSYm8xS3+AxRRZ2JAFJWJHSQqTQkbEzYwIM+6Yid/znJfH4prLXShOnb6r8/nWOd8Tcs78bz0/f+KMu50y05nK/wy+uHDylbutqS5extvGcxaWqtoGDA8PZ3dnrs2srQc2Zko41UXLmLdyDW5OfvsUkUgbYGbU63UAQggdmvMzFmzZCgTi7CQmkZwdEaX0JwDgTnGbTCaE0G4zw80omhPI92lcEtkNkdgJCCHwJX7mZvNaB0A14SaYJlwTrpHsTkoFlV1nt2c3x5YYo1/vM9A/gKpxdfwyu/v3teCayKq4JEwT5EB2R6WgYmrs2bYbcUNNUVfEhIfFYy69uci+1fuRX84mkawFSxd/4nVWUopUVIykwlQxRTJBTIDA4Pp1jBZPuNW4wUAPmCqWIn29X1k4f5Ku8g9mpKCkakRLVEs1auVuauVuyqHMo8ejNCe+sWPVTkQKXCMmkeZUmUZjETF1tc6ooly+fgUVw9So1/tRN6YnZji46QghBFKKuAouERNhMlbAHZFE6e7pB+He8MMw+GGI4xtOMf1+lsl3TQ4NHf19BSlaO1DB9BfMHdX0O0iqSgiBbJkjm491hClJbA1LxCURgpPzXwAHhg63necAIi3XngXLcRU0fof8ETMljIyM5LGxMcbHxzvy/6fuXdWgt6+PWncv1e4euqo1ZmabvHs5+jn8yzufO7hiiZmuNpNBM13rbvVSpbrXJE7/BMkHtU9jFIC/AAAAAElFTkSuQmCC") no-repeat center left; overflow: hidden; text-indent: 18px; line-height: 0px; cursor:pointer; margin-left:6px; color: #21759B;  text-decoration: underline;}
#logout:hover {text-decoration: none; color: #D54E21;}
#logins a, #tables a {background: none repeat scroll 0 0 transparent;}
body {margin: 0; line-height: 1.25em; font-size: 13px; background: #F9F9F9;}
body, select, option, optgroup, button {font-family: "Lucida Grande",Verdana,Arial,"Bitstream Vera Sans",sans-serif;} /* IE6 */
input[type='submit'], input[type='reset'], input[type='button'], input[type='file'] {font-family: "Lucida Grande",Verdana,Arial,"Bitstream Vera Sans",sans-serif;}
input, textarea, pre, code, samp, kbd, var {font-family: "Lucida Grande",Verdana,Arial,"Bitstream Vera Sans",sans-serif; font-size: 12px;}
a {color: #21759B;}
a:visited {color: #21759B;}
a:hover {text-decoration: none; color: #D54E21;}
form {margin: 0;}
table {margin: 10px 12px 12px 0; border: 1px #BBB solid; font-size: 90%;}
th {text-align: left;}
td, th {background-color: #fff; padding: 4px 6px; border: 1px #DfDfDf solid; border-width: 1px 0 0 1px;}
tr:first-child td, tr:first-child th {border-top-width: 0;}
tr:first-child th {padding-right: 30px;}
td:first-child, th:first-child {border-left-width: 0;}
thead td, thead th {background-color: #DFDFDF; border: none; border-bottom: 1px #BBB solid;}
thead tr:hover td, thead tr:hover th {background-color: #DDD !important;}
tr:nth-child(2n) td, tr:nth-child(2n) th, .odd td, .odd th, tr.odd {background-color: #F1F1F1;}
tr:hover td, tr:hover th {background-color: #BCD;}
fieldset {display: inline; vertical-align: top; padding: 2px 12px; margin: 25px 12px 12px 0; border: none; background-color: #F1F1F1; border: 1px solid #E3E3E3; position: relative; padding-top: 14px;}
fieldset, x:-moz-any-link {padding-top: 4px;}
fieldset {%padding-top: 14px;}
legend {font-weight: 900; color: #000; position: absolute; top: -1.666em; left: -1em; padding: 0 4px;}
input[name='limit'], input[name*='length'] {width: 3em; xtext-align: right;}
input[name='text_length'] {width: 5em;}
select + input, select + select {margin-left: 2px;}
textarea, input, select {border-width: 1px; border-style: solid; -moz-border-radius: 4px; -khtml-border-radius: 4px; -webkit-border-radius: 4px; border-radius: 4px; border-color: #DFDFDF;}
input[type="checkbox"], input[type="radio"], input[type="image"] {border: 0 none;}
input[type=button], input[type=submit] {border-color: #bbb; color: #464646;}
input[type=button]:hover, input[type=submit]:hover {color: #000; border-color: #666;}
input[type=button], input[type=submit] {text-decoration: none; font-size: 11px !important; line-height: 14px; padding: 2px 8px; cursor: pointer; border-width: 1px; border-style: solid; -moz-border-radius: 11px; -khtml-border-radius: 11px; -webkit-border-radius: 11px; border-radius: 11px; -moz-box-sizing: content-box; -webkit-box-sizing: content-box; -khtml-box-sizing: content-box; box-sizing: content-box;}
input + label input, select + label input {margin-left: 4px;}
td input[type='checkbox']:first-child, td input[type='radio']:first-child {margin-left: 2px;}
label:hover {text-decoration: underline;}
fieldset div {margin-bottom: 2px;}
input[name='Comment'] { /* !!! */ width: 24em;}
input[name='Auto_increment'] { /* !!! */width: 6em;}
img {vertical-align: middle; margin: 0; padding: 0;}
.error {padding: 8px; color: red; background-color: #FEE;}
.message {padding: 8px; background-color: #DDD;}
.char {color: #070;}
.date {color: #707;}
.enum {color: #077;}
.binary {color: red;}
.jush-sql {padding: 2px 4px; margin-right: 4px; outline: 1px #BBB dashed; font-size: 9pt;}
#content {margin: 2px 0 0 300px; padding: 10px 20px 20px 0;}
#lang {height: 23px; width: 250px; display: block; padding: 1px 10px; position: absolute; top: 0; left: 0; text-align: center; background-color: #f1f1f1; border: 1px solid #E3E3E3; line-height: 1.25em;}
#lang select {font-size: 8pt;}
#breadcrumb {margin: 0; height: 21px; display: block; position: absolute; top: 0; left: 300px; background-color: #f1f1f1; border: 1px solid #E3E3E3; padding: 2px 12px; line-height: 1.25em }
#menu {position: absolute; padding: 10px; margin: 0; top: 28px; left: 0; width: 250px; background-color: #f1f1f1; border: 1px solid #E3E3E3;}
#menu form {margin: 0;}
#menu p, #tables {padding-left: 8px; font-size: 10pt; border-bottom: none;}
#menu form p {padding-left: 0; text-align: left;}
h1 .h1:hover {text-decoration: underline;} 
h1, h2 {font: italic normal normal 24px/29px Georgia, "Times New Roman", "Bitstream Charter", Times, serif; margin: 0; padding: 14px 15px 3px 10px; line-height: 35px; text-shadow: rgba(255,255,255,1) 0 1px 0px; background: none;}
h1 {font-size: 12px;}
h1 .h1 {font-size: 12px;}
h2 {padding: 22px 0 0 10px;}
h3 {margin: 40px 0 0; font-weight: 400; font-size: 130%;}
#schema {margin: 1.5em 0 0 220px; position: relative;}
#schema .table {border: 1px solid #E3E3E3; background-color: #F1F1F1; padding: 0 2px; cursor: move; position: absolute;}
#schema .references {position: absolute;}
.js .hidden {display: inline;}
.js td.hidden, .js input.hidden {display: none;}
legend a {color: #333; text-decoration: none; cursor: default;}
legend a:hover {color: #333;}
code {background: transparent;}
fieldset, legend, h2, table, .error, .message {-moz-border-radius: 5px; -khtml-border-radius: 5px; -webkit-border-radius: 5px;border-radius: 5px;}
#breadcrumb, #lang, #menu {-moz-border-radius-bottomright: 5px; -khtml-border-bottom-right-radius: 5px; -webkit-border-bottom-right-radius: 5px; border-bottom-right-radius: 5px;}
#breadcrumb {-moz-border-radius-bottomleft: 5px; -khtml-border-bottom-left-radius: 5px; -webkit-border-bottom-left-radius: 5px; border-bottom-left-radius: 5px;}
#menu {-moz-border-radius-topright: 5px; -khtml-border-top-right-radius: 5px; -webkit-border-top-right-radius: 5px; border-bottom-top-radius: 5px;}
#loader {margin-left: 35px;}
EOT;
            exit;
    }
}

            
            
// Adminer customize / plugins
function adminer_object() {

    // plugins and plugin helper are stacked here in one file to avoid mess
    require_once (PATH_dump . 'lib/adminer-plugins.php');


    $plugins = [
        new AdminerEditTextarea(),
        //new AdminerVersionNoverify(), // causes errors
    ];
    
    // combine customization and plugins:
    class AdminerDUMPCustomizationAndPlugins extends AdminerPlugin {
        
        public function name() {
            return '\'.\' DUMP ADMINER';
        }
        
        function credentials() {
            global $dbConf;
            // note that these are not keynames from typo's config, but we use the dump's standarized format!
            return [$dbConf['host'], $dbConf['username'], $dbConf['password']];
        }
        
        public function database() {
            global $dbConf;
            return $dbConf['database'];
        }

        function login($login, $password) {
            return true;
        }

        function loginForm() {
            //global $autologinRedirectUri;
            //print '<a href="'.$autologinRedirectUri.'">'.$autologinRedirectUri.'</a>';
            
            global $dbConf;
            global $ADM_driver;
            
            print '</form> <form action="" method="post" id="custom_loginform">
                <table cellspacing="0" class="layout"><tbody>
                    <tr><th>System</th><td><input name="auth[driver]" value="'.$ADM_driver.'"></td></tr>
                    <tr><th>Server</th><td><input name="auth[server]" value="'.$dbConf['host'].'" title="hostname[:port]" placeholder="localhost" autocapitalize="off"></td></tr>
                    <tr><th>Username</th><td><input name="auth[username]" id="username" value="'.$dbConf['username'].'" autocomplete="username" autocapitalize="off"></td></tr>
                    <tr><th>Password</th><td><input name="auth[password]" type="password" value="'.$dbConf['password'].'" autocomplete="current-password"></td></tr>
                    <tr><th>Database</th><td><input name="auth[db]" value="'.$dbConf['database'].'" autocapitalize="off"></td></tr>
                </tbody></table>
                <label><input type="checkbox" name="auth[permanent]" value="1">Permanent login</label>';

            print '<p><input type="submit" value="Login"></p>';

            print '<script type="text/javascript" '.nonce().'>
                document.addEventListener("DOMContentLoaded", function(event) {
                    document.getElementById("custom_loginform").submit();
                })
            </script>';
        }

        function css() {
            $style_disposition = '?dump_disposition=adminer.css';
            return [
                "$style_disposition&v=" . crc32(file_get_contents(PATH_dump . 'lib/adminer-loader.php'))
            ];
        }
    }

    //return new AdminerPlugin($plugins);   // call mine instead
    return new AdminerDUMPCustomizationAndPlugins($plugins);
}


// The session inject way doesn't work as expected, so use the uglier but sure method:
// Prefill & autosubmit loginform 


// include original Adminer /  use "en", because multilang is broken somehow with output of lang labels if included like this
require (PATH_dump . 'lib/adminer-4.8.1-en.php');
