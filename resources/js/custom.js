
export function pressStar(numstar,text_star, star)
{
    var rate = 0;
    switch (parseInt(numstar)) {
        case 1:
            rate = 1;
            text_star.innerHTML = 1;
            star[0].querySelector(".fa").remove();
            star[0].querySelector(".icon").innerHTML  = '<i class="fa fa-star" aria-hidden="true"></i>';

            star[1].querySelector(".fa").remove();
            star[1].querySelector(".icon").innerHTML  = '<i class="fa fa-star-o" aria-hidden="true"></i>';

            star[2].querySelector(".fa").remove();
            star[2].querySelector(".icon").innerHTML  = '<i class="fa fa-star-o" aria-hidden="true"></i>';

            star[3].querySelector(".fa").remove();
            star[3].querySelector(".icon").innerHTML  = '<i class="fa fa-star-o" aria-hidden="true"></i>';

            star[4].querySelector(".fa").remove();
            star[4].querySelector(".icon").innerHTML  = '<i class="fa fa-star-o" aria-hidden="true"></i>';

            break;
        case 2:
            rate = 2;
            text_star.innerHTML = 2;
            star[0].querySelector(".fa").remove();
            star[0].querySelector(".icon").innerHTML  = '<i class="fa fa-star" aria-hidden="true"></i>';

            star[1].querySelector(".fa").remove();
            star[1].querySelector(".icon").innerHTML  = '<i class="fa fa-star" aria-hidden="true"></i>';

            star[2].querySelector(".fa").remove();
            star[2].querySelector(".icon").innerHTML  = '<i class="fa fa-star-o" aria-hidden="true"></i>';

            star[3].querySelector(".fa").remove();
            star[3].querySelector(".icon").innerHTML  = '<i class="fa fa-star-o" aria-hidden="true"></i>';

            star[4].querySelector(".fa").remove();
            star[4].querySelector(".icon").innerHTML  = '<i class="fa fa-star-o" aria-hidden="true"></i>';
            break;
        case 3:
            rate = 3;
            text_star.innerHTML = 3;
            star[0].querySelector(".fa").remove();
            star[0].querySelector(".icon").innerHTML  = '<i class="fa fa-star" aria-hidden="true"></i>';

            star[1].querySelector(".fa").remove();
            star[1].querySelector(".icon").innerHTML  = '<i class="fa fa-star" aria-hidden="true"></i>';

            star[2].querySelector(".fa").remove();
            star[2].querySelector(".icon").innerHTML  = '<i class="fa fa-star" aria-hidden="true"></i>';

            star[3].querySelector(".fa").remove();
            star[3].querySelector(".icon").innerHTML  = '<i class="fa fa-star-o" aria-hidden="true"></i>';

            star[4].querySelector(".fa").remove();
            star[4].querySelector(".icon").innerHTML  = '<i class="fa fa-star-o" aria-hidden="true"></i>';
            break;
        case 4:
            rate = 4;
            text_star.innerHTML = 4;
            star[0].querySelector(".fa").remove();
            star[0].querySelector(".icon").innerHTML  = '<i class="fa fa-star" aria-hidden="true"></i>';

            star[1].querySelector(".fa").remove();
            star[1].querySelector(".icon").innerHTML  = '<i class="fa fa-star" aria-hidden="true"></i>';

            star[2].querySelector(".fa").remove();
            star[2].querySelector(".icon").innerHTML  = '<i class="fa fa-star" aria-hidden="true"></i>';

            star[3].querySelector(".fa").remove();
            star[3].querySelector(".icon").innerHTML  = '<i class="fa fa-star" aria-hidden="true"></i>';

            star[4].querySelector(".fa").remove();
            star[4].querySelector(".icon").innerHTML  = '<i class="fa fa-star-o" aria-hidden="true"></i>';
            break;
        case 5:
            rate = 5;
            text_star.innerHTML = 5;
            star[0].querySelector(".fa").remove();
            star[0].querySelector(".icon").innerHTML  = '<i class="fa fa-star" aria-hidden="true"></i>';

            star[1].querySelector(".fa").remove();
            star[1].querySelector(".icon").innerHTML  = '<i class="fa fa-star" aria-hidden="true"></i>';

            star[2].querySelector(".fa").remove();
            star[2].querySelector(".icon").innerHTML  = '<i class="fa fa-star" aria-hidden="true"></i>';

            star[3].querySelector(".fa").remove();
            star[3].querySelector(".icon").innerHTML  = '<i class="fa fa-star" aria-hidden="true"></i>';

            star[4].querySelector(".fa").remove();
            star[4].querySelector(".icon").innerHTML  = '<i class="fa fa-star" aria-hidden="true"></i>';
            break;
        default:
            break;

    }

    return rate;
}



export function setCookie(name,value,days) {
    var expires = "";
    if (days) {
        var date = new Date();
        date.setTime(date.getTime() + (days*24*60*60*1000));
        expires = "; expires=" + date.toUTCString();
    }
    document.cookie = name + "=" + (value || "")  + expires + "; path=/;"+" SameSite=None; Secure";
}
export function getCookie(name) {
    var nameEQ = name + "=";
    var ca = document.cookie.split(';');
    for(var i=0;i < ca.length;i++) {
        var c = ca[i];
        while (c.charAt(0)==' ') c = c.substring(1,c.length);
        if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
    }
    return null;
}

