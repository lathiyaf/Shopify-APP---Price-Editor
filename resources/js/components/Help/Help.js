import React from 'react';

export default function Help() {
    const elementID = "help_content";
    return <div  id={"help_content"} style={{
        textAlign: "center"
    }} dangerouslySetInnerHTML={{__html: document.getElementById(elementID).innerHTML}}/>
}

import './Help.css';
