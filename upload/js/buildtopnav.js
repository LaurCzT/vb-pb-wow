var global_nav_lang, opt;

function buildtopnav(){

global_nav_lang = (global_nav_lang) ? global_nav_lang.toLowerCase() : "";

linkarray = new Array(); 
outstring = "";

switch(global_nav_lang){
 default:
    link1 = new Object();
    link1.text = "PBWoW"
    link1.href = "http://www.pbwow.com"
    
    link2 = new Object();
    link2.text = "phpBB Dev Thread"
    link2.href = "http://www.phpbb.com/community/viewtopic.php?f=74&t=1008155"
    
    link3 = new Object();
    link3.text = "PBWoW"
    link3.href = "http://www.pbwow.com/"
break;
 
}

//
linkarray.push(link1) 
linkarray.push(link2)
linkarray.push(link3)

for(i=0; i<linkarray.length; i++)
{ div = (i<linkarray.length-1) ? "<img src='/images/topnav_div.gif'/>":""
  outstring += "<a href='"+linkarray[i].href+ "'>"+ linkarray[i].text +"</a>"+div; }

	topnavguts = "";
	topnavguts += "<div class='topnav'><div class='tn_interior'>";
	topnavguts += outstring;
	topnavguts += "</div></div><div class='tn_push'></div>";
	
	if(document.location.href) hrefString = document.location.href; else hrefString = document.location;
	
	divclass = (hrefString.indexOf("forums")>=0)?"tn_forums":(hrefString.indexOf("armory")>=0)?"tn_armory":"tn_wow";

	targ = document.getElementById("shared_topnav");
    if(targ != null) {
    	targ.innerHTML = topnavguts;
    	if(!targ.className || targ.className == ""){targ.className = divclass;}
    	if(targ.className.indexOf("tn_armory")>=0){ document.body.style.backgroundPosition = "50% 26px"; }
    	if(targ.className.indexOf("tn_forums")>=0){ document.body.style.backgroundPosition = "100% 26px"; } 

    }
}

buildtopnav();