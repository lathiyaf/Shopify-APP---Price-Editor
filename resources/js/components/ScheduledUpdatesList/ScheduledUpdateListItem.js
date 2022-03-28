import React, {Component} from 'react';
import {
    ResourceItem,
    Button
} from '@shopify/polaris';



let currentStateRequest = null;
let progressRequest = null;

export default class ScheduledUpdateListItem extends Component {

    constructor(props) {
        super(props);
        const {
            shopInfo,
            shopDomain,
            id,
            updated,
            total,
            created_datetime,
            description,
            scheduling_description,
            status,
            sub_status,
            sub_status_text,
            status_text,
            file_name,
            file_url,
            updates_count,
            updated_products_count,
            reverted_updates_count,
            variants,
            errors,
            type,
            start_date,
            end_date,
            start_time,
            end_time,
            start_day,
            end_day,
            has_updates,
        } = props;
        this.state = {
            shopInfo: shopInfo,
            shopDomain: shopDomain,
            id: id,
            updated: updated,
            total: total,
            created_datetime: created_datetime,
            description: description,
            scheduling_description: scheduling_description,
            status: status,
            sub_status: sub_status,
            sub_status_text: sub_status_text,
            status_text: status_text,
            file_name: file_name,
            file_url: file_url,
            actionButtonsLoading: false,
            reverted_updates_count: reverted_updates_count,
            updated_products_count: updated_products_count,
            updates_count: updates_count,
            variants: variants,
            errors: errors,
            type: type,
            start_date: start_date,
            end_date: end_date,
            start_time: start_time,
            end_time: end_time,
            start_day: start_day,
            end_day: end_day,
            has_updates: has_updates,
        };



        this.handleStateAction = this.handleStateAction.bind(this);
        this.updateProgress = this.updateProgress.bind(this);
        this.handleOpenEditItem = this.handleOpenEditItem.bind(this);
    }

    handleStateAction(newState) {


        if(newState === 'canceled'){
            // if(!confirm('Are you sure?')){
            //     return;
            // }
        }

        this.setState({
            actionButtonsLoading: true
        });
        this.props.onRefreshChildren();

        currentStateRequest = $.ajax({
            url: '/changeSchedulingUpdateStatus',
            method: 'POST',
            data: {status: newState, id: this.state.id},
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
            }
        });
        currentStateRequest.then(data => {
            this.setState({
                actionButtonsLoading: false,
                total: data.total,
                updated: data.updated,
                status: data.status,
                status_text: data.status_text
            });
            this.props.onChangeItemState();
        });
    }


    updateProgress() {

        if(this.state.sub_status !== 'running' && this.state.sub_status !== 'reverting'){
            return;
        }

        progressRequest = $.ajax({
            url: '/getScheduledUpdateProgressInfo/'+this.state.id,
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
            }
        });
        progressRequest.then(data => {
            this.setState({
                total: data.total,
                id: data.id,
                updated: data.updated,
                status: data.status,
                sub_status: data.sub_status,
                status_text: data.status_text,
                reverted_updates_count: data.reverted_updates_count,
                updated_products_count: data.updated_products_count,
                updates_count: data.updates_count,
                errors: data.errors,
            });
            this.props.onRefreshChildren();
        });
    }

    componentDidMount() {
        this.intervalId = setInterval(this.updateProgress.bind(this), 5000);
    }

    componentWillUnmount() {
        clearInterval(this.intervalId);
    }

    handleOpenEditItem(){
        let handleEditItem = this.props.handleEditItem;
        handleEditItem({
            type: this.state.type,
            start_date: this.state.start_date,
            end_date: this.state.end_date,
            start_time: this.state.start_time,
            end_time: this.state.end_time,
            start_day: this.state.start_day,
            end_day: this.state.end_day,
            id: this.state.id,
        });
    }

    render() {
        let dateInfo = '';
        let descInfo = '';
        let schedulingDescInfo = '';
        let statusInfo = '';
        let reportInfo = '';
        let actionsInfo = '';
        let progressInfo = '';

        if (this.state.id !== 0) {


            dateInfo = (
                <div className="ScheduledUpdateListItem__Flex1 ScheduledUpdateListItem__FlexPrice">
                    <p>{this.state.created_datetime}</p>
                </div>
            );

            descInfo = (
                <div className="ProductListItem__Flex2">
                    <p>{this.state.description}</p>
                </div>
            );

            schedulingDescInfo = (
                <div className="ProductListItem__Flex2">
                    <p>{this.state.scheduling_description}</p>
                </div>
            );


            let subStatusInfo = "";
            if(this.state.sub_status_text){
                subStatusInfo = (
                    <p>Last update: {this.state.sub_status_text}</p>
                )
            }

            statusInfo = (
                <div className="ScheduledUpdateListItem__Flex1 ScheduledUpdateListItem__FlexPrice">
                    <p>{this.state.status_text}</p>
                    {subStatusInfo}
                </div>
            );


            let update_text = (
                <p>There hasn't been any update so far</p>
            );

            if(this.state.has_updates){
                update_text = (
                    <p>Updated {this.state.updated}/{this.state.total} variants</p>
                );
                if(!this.state.variants){
                    update_text = (
                        <p>Updated {this.state.updates_count} variants in {this.state.updated}/{this.state.total} products</p>
                    );
                }
            }


            if(this.state.reverted_updates_count > 0){
                progressInfo = (
                    <div className="ScheduledUpdateListItem__Flex1 ScheduledUpdateListItem__FlexPrice">
                        {update_text}
                        <p>Reverted {this.state.reverted_updates_count} variants from {this.state.updates_count} variants in {this.state.updated_products_count} products</p>
                    </div>
                );
            } else {
                progressInfo = (
                    <div className="ScheduledUpdateListItem__Flex1 ScheduledUpdateListItem__FlexPrice">
                        {update_text}
                    </div>
                );
            }

            let errorsHtml = '';

            this.state.errors.forEach(function(entry) {
               let fileUrl = entry.file_url;
               let fileName = entry.file_name;
                errorsHtml += '<div><p><a target="_blank" href="'+fileUrl+'">'+fileName+'</a></p></div>';
            });
            reportInfo = (
                <div className="ScheduledUpdateListItem__Flex1 ScheduledUpdateListItem__FlexPrice ScheduledUpdateListItem__FlexReport">
                    <div><p><a target={"_blank"} href={this.state.file_url}>{this.state.file_name}</a></p></div>
                    <div dangerouslySetInnerHTML={{__html: errorsHtml}}></div>
                </div>
            );

            let editButton = '';
            let cancelButton = '';

            if (this.state.status !== 'canceled') {
                cancelButton = (
                    <div className={'cancelButton'}>
                        <Button onClick={() => this.handleStateAction('canceled')} loading={this.state.actionButtonsLoading}>
                            Cancel
                        </Button>
                    </div>
                );

                editButton = (
                    <div className={'restartButton'}>
                        <Button onClick={() => this.handleOpenEditItem()}>
                            Edit
                        </Button>
                    </div>
                );
            }


            actionsInfo = (
                <div className="ProductListItem__Flex1 ScheduledUpdateListItem__FlexButtons">
                    {editButton}
                    {cancelButton}
                </div>
            );

        }

        let title = this.state.created_datetime + " "+this.state.description
        return (
            <ResourceItem
                id={this.state.id}
                accessibilityLabel={`View details for ${title}`}
                name={title}
            >
                <div className="ScheduledUpdateListItem__Main">
                    {dateInfo}
                    {descInfo}
                    {schedulingDescInfo}
                    {statusInfo}
                    {progressInfo}
                    {reportInfo}
                    {actionsInfo}
                </div>
            </ResourceItem>
        );
    }
}

import './ScheduledUpdateListItem.css';
