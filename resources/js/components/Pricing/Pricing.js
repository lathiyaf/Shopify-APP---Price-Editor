import React, {Component} from 'react';
import $ from "jquery";

export default class Pricing extends Component{


    constructor(props) {
        super(props);
    }


    render() {
        const elementID = "pricing_content";
        return <div  id={"pricing_content"} style={{
            textAlign: "center"
        }} dangerouslySetInnerHTML={{__html: document.getElementById(elementID).innerHTML}}/>

    }

    componentWillUnmount(){

    }


    componentDidMount() {

    }




}

import './Pricing.css';