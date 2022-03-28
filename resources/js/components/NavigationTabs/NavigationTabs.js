import React, {Component} from 'react';
import {AppProvider, Tabs, Card} from '@shopify/polaris';
import ProductsList from './../ProductsList';
import UpdatesList from './../UpdatesList';
import ScheduledUpdatesList from './../ScheduledUpdatesList';
import ContactUs from "../ContactUs/ContactUs";
import Help from "../Help/Help";
import LeaveFeedback from "../LeaveFeedback/LeaveFeedback";
import Pricing from "../Pricing/Pricing";

import en from '@shopify/polaris/locales/en.json';

class NavigationTabs extends Component {
    constructor(props) {
        super(props);

        this.handleTabChange = this.handleTabChange.bind(this);

        let {
            showTabs
        } = props;

        if(isNaN(showTabs)){
            showTabs = 1;
        }

        this.state = {
            selectedTab: 0,
            showTabs: showTabs
        };
    }

    handleTabChange(selectedTab) {
        this.setState({selectedTab});
        let showTabs = 1;
        if(selectedTab !== 0){
            showTabs = 0;
        }
        this.setState({
            showTabs: showTabs
        });
    }

    render() {
        let {selectedTab} = this.state;

        let tabs = [];
        let tabPanels = [];

        if(!this.state.showTabs) {
            selectedTab = 0;
            tabs = [
                {
                    // id: 'tab1',
                    title: 'products',
                    // panelID: 'products',
                    content: 'Back',
                    id: 'products-tab',
                    panelID: 'products-panel'
                },
            ];

            if(SHOPIFY_IS_PRO){
                switch(this.state.selectedTab){

                    case 5: {
                        tabPanels.push(
                            (
                                <Card.Section id="products-tab" tabID="products-panel">
                                    <LeaveFeedback/>
                                </Card.Section>
                            )
                        );
                        break;
                    }
                    case 4: {
                        tabPanels.push(
                            (

                                <Card.Section id="products-tab" tabID="products-panel">
                                    <Help/>
                                </Card.Section>
                            )
                        );
                        break;
                    }
                    case 3: {
                        tabPanels.push(
                            (
                                <Card.Section id="products-tab" tabID="products-panel">
                                    <Pricing/>
                                </Card.Section>
                            )
                        );
                        break;
                    }
                    case 2: {
                        tabPanels.push(
                            (
                                <Card.Section id="products-tab" tabID="products-panel">
                                    <ScheduledUpdatesList/>
                                </Card.Section>
                            )
                        );
                        break;
                    }
                    case 1: {
                        tabPanels.push(
                            (
                                <Card.Section id="products-tab" tabID="products-panel">
                                    <UpdatesList/>
                                </Card.Section>
                            )
                        );
                        break;
                    }
                    default: {
                        tabPanels.push(
                            (
                                <Card.Section id="products-tab" tabID="products-panel">
                                    <ProductsList/>
                                </Card.Section>
                            )
                        );
                        break;
                    }
                }

            } else {
                switch(this.state.selectedTab){

                    case 3: {
                        tabPanels.push(
                            (
                                <Card.Section id="products-tab" tabID="products-panel">
                                    <LeaveFeedback/>
                                </Card.Section>
                            )
                        );
                        break;
                    }
                    case 2: {
                        tabPanels.push(
                            (

                                <Card.Section id="products-tab" tabID="products-panel">
                                    <Help/>
                                </Card.Section>
                            )
                        );
                        break;
                    }
                    case 1: {
                        tabPanels.push(
                            (
                                <Card.Section id="products-tab" tabID="products-panel">
                                    <Pricing/>
                                </Card.Section>
                            )
                        );
                        break;
                    }
                    default: {
                        tabPanels.push(
                            (
                                <Card.Section id="products-tab" tabID="products-panel">
                                    <ProductsList/>
                                </Card.Section>
                            )
                        );
                        break;
                    }
                }

            }


        } else {
            tabs = [
                {
                    // id: 'tab1',
                    title: 'products',
                    // panelID: 'products',
                    content: 'Products',
                    id: 'products-tab',
                    panelID: 'products-panel'
                }
            ];


            tabPanels = [
                (
                    <Card.Section id="products-tab" tabID="products-panel">
                        <ProductsList/>
                    </Card.Section>
                ),
            ];




            if(SHOPIFY_IS_PRO){
                tabs.push(
                    {
                        title: 'updates',
                        // panelID: 'products',
                        content: 'History',
                        id: 'updates-tab',
                        panelID: 'updates-panel'
                    },
                    {
                        title: 'scheduled_updates',
                        // panelID: 'products',
                        content: 'Scheduling',
                        id: 'scheduled_updates-tab',
                        panelID: 'scheduled_updates-panel'
                    }
                );

                tabPanels.push(
                    (
                        <Card.Section id="scheduled_updates-tab" tabID="scheduled_updates-panel" ref={this.historyTab}>
                            <UpdatesList/>
                        </Card.Section>
                    ),
                    (
                        <Card.Section id="scheduled_updates-tab" tabID="scheduled_updates-panel" ref={this.schedulingTab}>
                            <ScheduledUpdatesList/>
                        </Card.Section>
                    )
                );
            }

            tabs.push(
                {
                    id: 'pricing',
                    title: 'pricing',
                    panelID: 'pricing-panel',
                    content: 'Pricing',

                },
                {
                    id: 'help',
                    title: 'Help',
                    panelID: 'help-panel',
                    content: 'Help',

                },
                {
                    id: 'feedback',
                    title: 'feedback',
                    panelID: 'feedback-panel',
                    content: "Leave a Review"

                }
            );

            tabPanels.push(
                (
                    <Card.Section id="pricing_content" tabID="pricing-panel">
                        <Pricing/>
                    </Card.Section>
                ),
                (
                    <Card.Section id="help_content" tabID="help-panel">
                        <Help/>
                    </Card.Section>
                ),
                (
                    <Card.Section id="feedback_content" tabID="feedback-panel">
                        <LeaveFeedback/>
                    </Card.Section>
                )
            );

        }




        return (
            <Card>
                <Tabs
                    selected={selectedTab}
                    tabs={tabs}
                    onSelect={this.handleTabChange}
                >
                    {tabPanels[selectedTab]}
                </Tabs>
            </Card>
        );
    }
}

export default NavigationTabs;
