import React from 'react';

export default function ContactUs() {
    const elementID = "contactus_content";
    return <div  id={"contactus_content"} style={{
        textAlign: "center"
    }} dangerouslySetInnerHTML={{__html: document.getElementById(elementID).innerHTML}}/>
}