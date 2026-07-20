// StarsT v1.0 by MaxxBlade
// http://www.maxxblade.co.uk/starst

var numstars=15, dist=100, startext="&#9786;", fontface="arial", fontsize=50, speed=2, spin=0, starcolor="000099", backcolor="ffffff";

var star=new Array(),moz=(document.getElementById&&!document.all)?1:0,h="0123456789abcdef",col1 = new Array(h2d(backcolor.substr(0,2)),h2d(backcolor.substr(2,2)),h2d(backcolor.substr(4,2))),col2 = new Array(h2d(starcolor.substr(0,2)),h2d(starcolor.substr(2,2)),h2d(starcolor.substr(4,2)));
function h2d(x){ return (16*(h.indexOf(x.substr(0,1))))+(h.indexOf(x.substr(1,1))); }
function d2h(x){ return h.substr(parseInt(x/16),1)+h.substr((x%16),1); }
function newStar(x){ star[x] = new Array(0, Math.random()*(2*Math.PI), Math.random()*(dist/4)); }
function starsT(){
	winWid=(moz)?window.innerWidth:document.documentElement.clientWidth;
	winHei=(moz)?window.innerHeight:document.documentElement.clientHeight;
	zero=(moz)?window.pageYOffset:document.documentElement.scrollTop;
	for (n=0;n<numstars;n++){
		if(star.length<numstars){ newStar(n); document.write('<DIV id="star'+n+'" style="position:absolute;font-family:'+fontface+';">'+startext+'</DIV>');	}
		star[n][0]+=speed; star[n][1]+=spin;
		yp=(winHei/2)+(star[n][0]*(Math.sin(star[n][1])*star[n][2]));
		xp=(winWid/2)+(star[n][0]*(Math.cos(star[n][1])*star[n][2]));
		if((star[n][0]>=dist)||(xp>=(winWid-(fontsize*2)))||(yp>=(winHei-(fontsize*2)))||(xp<=0)||(yp<=0)){ newStar(n); }
		with ( eval("document.getElementById('star'+n).style") ){
			color="#"+d2h(parseInt(col1[0]+((col2[0]-col1[0])/dist)*star[n][0]))+d2h(parseInt(col1[1]+((col2[1]-col1[1])/dist)*star[n][0]))+d2h(parseInt(col1[2]+((col2[2]-col1[2])/dist)*star[n][0]));
			top=yp+zero+"px";
			left=xp+"px";
			fontSize=1+parseInt((fontsize/dist)*star[n][0])+"px";
		}
		document.getElementById('star'+n).innerHTML=startext;
	}
	moving = setTimeout('starsT()',30);
}
starsT();