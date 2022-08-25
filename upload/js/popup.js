function popUp (url, width, height, name) {

		widthHeight = "width=" + width + ",height=" + height;
		winFeatures = "width=" + width + ",height=" + height + ",menubar=no,resizable=no,scrollbars=yes,status=no,toolbar=no,location=no"
		spawn = window.open(url,name,winFeatures);
		spawn.focus();

}

