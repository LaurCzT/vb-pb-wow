

function tree(pageId) {


	var treeArray = new Array();
	treeArray = pageId.split('_');
	var treeString = "";
	NoOffFirstLineMenus = treeArray.length;

	for (i = 0; i < treeArray.length; i++ ) {

		treeString = treeString + treeArray[i];

		if (i != 0)
			copyBranch(treeString,'_');
		
		treeString = treeString + "_";
	
	}
	

	if (eval('Menu' + pageId + '[3]') == 0) {
		NoOffFirstLineMenus = treeArray.length + 1;
		eval('Menu' + NoOffFirstLineMenus + '=new Array("<div style = position:absolute><img src = /images/subnav/dot.gif></div>","#","/images/subnav/button_bg.gif",0,15,13,"","","","","","",-1,-1,-1,"","");');
	}

}



var menuNum;
var result;

function copyBranch(startPoint, init) {
	var branchLength = eval('Menu' + startPoint + '[3]');
	var sourcePoint = new Array();
	sourcePoint = startPoint.split('_');
	var middle = init;

	

	if (middle == '_') {
		
		menuNum = sourcePoint.length;
		eval("Menu" + menuNum + "=Menu" + startPoint);
		eval("Menu" + menuNum + "[2]='/images/subnav/button_bg.gif'");
		
		if (Browser.ie && eval("Menu" + menuNum + "[0].indexOf('<img')!=-1")){
			var stripArray = eval("Menu" + menuNum + "[0].split('<img src=/images/subnav/bullet-trans-dot-blue.gif align=left />')")
			var stripArray = stripArray[1].split('<img src=/images/subnav/bullet-trans-line-blue.gif />')	
			var stripString	= stripArray[0];
			eval("Menu" + menuNum + "[0]='" + stripString +"'");	
		}
		

	}

	for (var i=1; i <= branchLength; i++) {
		
		
		destString = menuNum + middle + i;
		

		eval("Menu" + destString + "=Menu" + startPoint + "_" + i);

		
		
		if (eval('Menu' + startPoint + "_" + i + '[3]') != 0)
			copyBranch(startPoint + "_" + i,middle + i + '_');

	}

}

var urlString = document.location.href;
var forumsBool=1;

var urlStringMax = urlString.length;
	
urlString = urlString.substring(urlString.indexOf("//")+2, urlStringMax);
urlString = urlString.substring(urlString.indexOf("/"), urlStringMax);

if (urlString.indexOf("index.") != -1)
	urlStringMax = urlString.indexOf("index.");
else if (urlString.indexOf(".") != -1)
	urlStringMax = urlString.indexOf(".");
else
	urlStringMax = urlString.length;

urlString = urlString.substring(0, urlStringMax);

function findNode(startPoint, init, searchString) {

	
	var branchLength = eval('Menu' + startPoint + '[3]');
	var sourcePoint = new Array();
	sourcePoint = startPoint.split('_');
	var middle = init;
	var nodeUrl;
	
	if (searchString == "Game Guide") {
		result = "1";
		return;
	}
	
	
	
	if (middle == '_') {
		menuNum = sourcePoint.length;
	}

	for (var i=1; i <= branchLength; i++) {	
		
		destString = menuNum + middle + i;
		
		nodeUrl = eval("Menu" + startPoint + "_" + i + "[1]");
		
		
		
		if (nodeUrl.indexOf("index.") != -1)
			urlStringMax = nodeUrl.indexOf("index.");
		else if (nodeUrl.indexOf(".") != -1)
			urlStringMax = nodeUrl.indexOf(".");
		else
			urlStringMax = nodeUrl.length;
			
		nodeUrl = nodeUrl.substring(0, urlStringMax);
		
		
		if ((urlString.indexOf("/account/") != -1) || forumsBool || (urlString.indexOf("/contests/") != -1) || (urlString.indexOf("/loginsupport/") != -1)){
			nodeUrl = urlString;
		}
		
		if((eval("Menu" + startPoint + "_" + i + "[0]") == searchString) && (nodeUrl == urlString)) {
			result = startPoint + "_" + i;
			return;
		}
	

		
		if (eval('Menu' + startPoint + "_" + i + '[3]') != 0)
			findNode(startPoint + "_" + i,middle + i + '_',searchString);

	}

}


var firstChar=pageId.substring(0,1);
if ((firstChar==1||firstChar==2||firstChar==3||firstChar==4||firstChar==5||firstChar==6||firstChar==7||firstChar==8||firstChar==9)&&pageId.indexOf("_")>-1){
	result=pageId;
	pageId=eval("Menu"+pageId+"[0]")
}else{
	if(Browser.ie)
		pageId="<img src=/images/subnav/bullet-trans-dot-blue.gif align=left />"+pageId+"<img src=/images/subnav/bullet-trans-line-blue.gif />";
		
	findNode('1','_',pageId);
}

function printSubNav(treeId, mode) {

	var navLength;
	eval("navLength=Menu" + treeId + "[3]")
	
	if(mode == 1) {

		for (i=1; i <= navLength; i++) {

			var stripString=stripId(eval("Menu" + treeId + "_" + i + "[0]"));

			document.write("<a href = '" + eval("Menu" + treeId + "_" + i + "[1]") + "' class = 'nav'>" + stripString + "</a>")
			if (i < navLength)
				document.write(" | ");
		}
		
		
		
	}
	
	if(mode == 2) {
	
	
		if (navLength>0){
			
			
			document.write("<a href = '" + eval("Menu" + treeId + "[1]") + "'>" + stripId(eval("Menu" + treeId + "[0]")) + "</a> > ")
			for (i=1; i <= navLength; i++) {

			
				var stripString=stripId(eval("Menu" + treeId + "_" + i + "[0]"));
		
				if (stripString != stripId(pageId)) {

					document.write("<a href = '" + eval("Menu" + treeId + "_" + i + "[1]") + "'><nobr>" + stripString + "</nobr></a>")
					if (i < navLength)
						document.write(" | ");
				}
			}
			document.write("<p>");
		}
	
	
	}
	
	if(mode == 3) {
	
		if (navLength>0){
			
		
			if (stripId(eval("Menu" + treeId + "[0]")) != pageId)
				document.write("<a href = '" + eval("Menu" + treeId + "[1]") + "' class = 'nav'>" + eval("Menu" + treeId + "[0]") + "</a> > ")
			else
				document.write("<span style = 'font-family:verdana, arial, sans-serif; font-size:10px; font-weight:bold; color:white;'>" + eval("Menu" + treeId + "[0]") + "</span> > ")
			for (i=1; i <= navLength; i++) {

				var stripString=stripId(eval("Menu" + treeId + "_" + i + "[0]"));

				if (stripString != stripId(pageId)) {


					document.write("<a href = '" + eval("Menu" + treeId + "_" + i + "[1]") + "' class = 'nav'><nobr>" + stripString + "</nobr></a>")
					if (i < navLength)
						document.write(" | ");
				} else {


					document.write("<span style = 'font-family:verdana, arial, sans-serif; font-size:10px; font-weight:bold; color:white;'><nobr>" + stripString + "</nobr></span>")
					if (i < navLength)
						document.write(" | ");
				
				}
			}

		}
	
	}	
	

}

function stripId(idString){

			
			if(Browser.ie && idString.indexOf('<img')!=-1){
				
				var stripArray = idString.split('<img src=/images/subnav/bullet-trans-dot-blue.gif align=left />');
				var stripArray = stripArray[1].split('<img src=/images/subnav/bullet-trans-line-blue.gif />')	
				var stripString	= stripArray[0];
				return stripString;
				
			}else{
				
				return idString;
			}		

}


function printRelatedLinks(treeId) {
	
	
	var treeArray = new Array();
	treeArray = treeId.split('_');
	var treeString = "";
	
	for (i = 0; i < treeArray.length-1; i++ ) {

		treeString = treeString + treeArray[i];

		if (i != treeArray.length-2)
			treeString = treeString + "_";
	
	}	
	
	printSubNav(treeString,2);

}



function printSubNav2(treeId) {
	
	
	var treeArray = new Array();
	treeArray = treeId.split('_');
	var treeString = "";
	
	for (i = 0; i < treeArray.length-1; i++ ) {

		treeString = treeString + treeArray[i];

		if (i != treeArray.length-2)
			treeString = treeString + "_";
	
	}	
	
	if (eval("Menu" + treeId + "[3]") == 0)
		printSubNav(treeString,3);
	else
		printSubNav(result,3);

}

tree(result);
