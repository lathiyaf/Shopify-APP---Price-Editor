import React from 'react';
import {
    ResourceItem,
    Image
} from '@shopify/polaris';


Array.prototype.sum = function (prop) {
    let total = 0;
    for ( let i = 0, _len = this.length; i < _len; i++ ) {
        total += this[i][prop]
    }
    return total
};


Array.prototype.price = function (prop, currencySymbol) {
    let price = '';
    for ( let i = 0, _len = this.length; i < _len; i++ ) {
        let p = parseFloat(this[i][prop]);
        if(isNaN(p)){
            p = 0;
        }

        price += '<p class="prices_list_item">'+this[i]['title'] + ' - ' + currencySymbol + ' ' + p  + '</p>';
    }
    return price
};

function ProductListItem(props) {
    const {
        shopInfo,
        shopDomain,
        id,
        product_id,
        image,
        title,
        variant_title,
        price,
        compare_at_price,
        inventory_quantity,
        vendor,
        product_type,
        ...rest
    } = props;

    let productUrl = "https://"+shopDomain+"/admin/products/"+product_id;
    let variantUrl = "https://"+shopDomain+"/admin/products/"+product_id+'/variants/'+id;

    let mainInfo = '';
    let media = '';
    let priceInfo = '';
    let compareAtPriceInfo = '';
    let inventoryInfo = '';
    let vendorInfo = '';
    let typeInfo = '';

    if(id !== 0){

        let img = '';
        if(image){
            img = image.src;
        }

        media = (
            <div className="ProductListItem__Media">
                <Image alt={title} source={img}  size="medium" />
            </div>
        );


        if(variant_title !== ''){
            mainInfo = (
                <div className="ProductListItem__Info ProductListItem__Flex2">
                    <div><p><a target={"_blank"} href={productUrl}>{title}</a><br/><a className={"variantUrl"} target={"_blank"} href={variantUrl}>{variant_title}</a></p></div>
                </div>
            );
        } else {
            mainInfo = (
                <div className="ProductListItem__Info ProductListItem__Flex2">
                    <div><p><a target={"_blank"} href={productUrl}>{title}</a></p></div>
                </div>
            );
        }

        const price_string = '<p class="prices_list_item prices_list_item_title">Price</p>'+'<p class="prices_list_item">'+shopInfo.currencySymbol + price+'</p>';
        priceInfo = (
            <div className="ProductListItem__Flex1 ProductListItem__FlexPrice" dangerouslySetInnerHTML={{__html: price_string}}></div>
        );

        let compare_at_price_string = '';
        if(compare_at_price){
            compare_at_price_string = '<p class="prices_list_item prices_list_item_title">Compare At Price</p>'+'<p class="prices_list_item">'+shopInfo.currencySymbol + compare_at_price+'</p>';

        } else {
            compare_at_price_string = '<p class="prices_list_item prices_list_item_title">Compare At Price</p>'+'<p class="prices_list_item"></p>';
        }
        compareAtPriceInfo = (
            <div className="ProductListItem__Flex1" dangerouslySetInnerHTML={{__html: compare_at_price_string}}></div>
        );



        inventoryInfo = (
            <div className="ProductListItem__Flex1">
                <p><span className={"warning"}>{inventory_quantity}</span> in stock</p>
            </div>
        );


        typeInfo = (
            <div className="ProductListItem__Flex1">
                <p>{product_type}</p>
            </div>
        );


        vendorInfo = (
            <div className="ProductListItem__Flex1">
                <p>{vendor}</p>
            </div>
        );
    }

    return (
        <ResourceItem
            media={media}
            id={id}
            accessibilityLabel={`View details for ${title}`}
            name={title}
        >
            <div className="ProductListItem__Main">
                {mainInfo}
                {priceInfo}
                {compareAtPriceInfo}
                {inventoryInfo}
                {typeInfo}
                {vendorInfo}
            </div>
        </ResourceItem>
    );
}

import './ProductListItem.css';

export default ProductListItem
