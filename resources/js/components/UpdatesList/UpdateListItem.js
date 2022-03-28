import React, {Component} from 'react';
import {
    ResourceItem,
    Button
} from '@shopify/polaris';



let currentStateRequest = null;
let progressRequest = null;

export default class UpdateListItem extends Component {

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
            status,
            status_text,
            file_name,
            file_url,
            updates_count,
            updated_products_count,
            reverted_updates_count,
            variants,
            errors
        } = props;
        this.state = {
            shopInfo: shopInfo,
            shopDomain: shopDomain,
            id: id,
            updated: updated,
            total: total,
            created_datetime: created_datetime,
            description: description,
            status: status,
            status_text: status_text,
            file_name: file_name,
            file_url: file_url,
            actionButtonsLoading: false,
            reverted_updates_count: reverted_updates_count,
            updated_products_count: updated_products_count,
            updates_count: updates_count,
            variants: variants,
            errors: errors,
        };



        this.handleStateAction = this.handleStateAction.bind(this);
        this.updateProgress = this.updateProgress.bind(this);
    }

    handleStateAction(newState) {


        if(newState === 'reverting_finished' || newState === 'reverting' || newState === 'finished'){
            // if(!confirm('Are you sure?')){
            //     return;
            // }
        }

        this.setState({
            actionButtonsLoading: true
        });
        this.props.onRefreshChildren();

        currentStateRequest = $.ajax({
            url: '/changeUpdateStatus',
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

        if(this.state.status !== 'running' && this.state.status !== 'reverting'){
            return;
        }

        progressRequest = $.ajax({
            url: '/getUpdateProgressInfo/'+this.state.id,
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
            }
        });
        progressRequest.then(data => {
            this.setState({
                total: data.total,
                updated: data.updated,
                status: data.status,
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

    render() {
        let dateInfo = '';
        let descInfo = '';
        let statusInfo = '';
        let reportInfo = '';
        let actionsInfo = '';
        let progressInfo = '';


        if (this.state.id !== 0) {
            dateInfo = (
                <div className="UpdateListItem__Flex1 UpdateListItem__FlexPrice">
                    <p>{this.state.created_datetime}</p>
                </div>
            );

            descInfo = (
                <div className="ProductListItem__Flex2">
                    <p>{this.state.description}</p>
                </div>
            );

            statusInfo = (
                <div className="UpdateListItem__Flex1 UpdateListItem__FlexPrice">
                    <p>{this.state.status_text}</p>
                </div>
            );


            let update_text = (
                <p>Updated {this.state.updated}/{this.state.total} variants</p>
            );
            if(!this.state.variants){
                update_text = (
                    <p>Updated {this.state.updates_count} variants in {this.state.updated}/{this.state.total} products</p>
                );
            }

            if(this.state.reverted_updates_count > 0){
                progressInfo = (
                    <div className="UpdateListItem__Flex1 UpdateListItem__FlexPrice">
                        {update_text}
                        <p>Reverted {this.state.reverted_updates_count} variants from {this.state.updates_count} variants in {this.state.updated_products_count} products</p>
                    </div>
                );
            } else {
                progressInfo = (
                    <div className="UpdateListItem__Flex1 UpdateListItem__FlexPrice">
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
                <div className="UpdateListItem__Flex1 UpdateListItem__FlexPrice UpdateListItem__FlexReport">
                    <div><p><a target={"_blank"} href={this.state.file_url}>{this.state.file_name}</a></p></div>
                    <div dangerouslySetInnerHTML={{__html: errorsHtml}}></div>
                </div>
            );

            let pauseButton = '';
            let cancelButton = '';
            let resumeButton = '';
            let restartButton = '';
            let rollbackButton = '';
            let pauseRollbackButton = '';
            let cancelRollbackButton = '';
            let resumeRollbackButton = '';
            let restartRollbackButton = '';

            if (this.state.status === 'running') {
                pauseButton = (
                    <div className={'pauseButton'}>
                        <Button onClick={() => this.handleStateAction('paused')} loading={this.state.actionButtonsLoading}>
                            Pause
                        </Button>
                    </div>
                );

                cancelButton = (
                    <div className={'cancelButton'}>
                        <Button onClick={() => this.handleStateAction('finished')} loading={this.state.actionButtonsLoading}>
                            Cancel
                        </Button>
                    </div>
                );
            }
            if (this.state.status === 'paused') {
                resumeButton = (
                    <div className={'resumeButton'}>
                        <Button onClick={() => this.handleStateAction('running')} loading={this.state.actionButtonsLoading}>
                            Resume
                        </Button>
                    </div>
                );
            }

            if (this.state.status === 'failed') {
                restartButton = (
                    <div className={'restartButton'}>
                        <Button onClick={() => this.handleStateAction('running')} loading={this.state.actionButtonsLoading}>
                            Restart
                        </Button>
                    </div>
                );
                cancelButton = (
                    <div className={'cancelButton'}>
                        <Button onClick={() => this.handleStateAction('finished')} loading={this.state.actionButtonsLoading}>
                            Cancel
                        </Button>
                    </div>
                );
            }

            if (this.state.status === 'finished') {
                rollbackButton = (
                    <div className={'rollbackButton'}>
                        <Button onClick={() => this.handleStateAction('reverting')} loading={this.state.actionButtonsLoading}>
                            Rollback
                        </Button>
                    </div>
                );
            }

            if (this.state.status === 'reverting') {
                pauseRollbackButton = (
                    <div className={'pauseRollbackButton'}>
                        <Button onClick={() => this.handleStateAction('reverting_paused')} loading={this.state.actionButtonsLoading}>
                            Pause Rollback
                        </Button>
                    </div>
                );

                cancelRollbackButton = (
                    <div className={'cancelRollbackButton'}>
                        <Button onClick={() => this.handleStateAction('reverting_finished')} loading={this.state.actionButtonsLoading}>
                            Cancel Rollback
                        </Button>
                    </div>
                )
            }

            if (this.state.status === 'reverting_paused') {
                resumeRollbackButton = (
                    <div className={'resumeRollbackButton'}>
                        <Button onClick={() => this.handleStateAction('reverting')} loading={this.state.actionButtonsLoading}>
                            Resume Rollback
                        </Button>
                    </div>
                );
            }

            if (this.state.status === 'reverting_failed') {
                restartRollbackButton = (
                    <div className={'restartRollbackButton'}>
                        <Button onClick={() => this.handleStateAction('reverting')} loading={this.state.actionButtonsLoading}>
                            Restart Rollback
                        </Button>
                    </div>
                );

                cancelRollbackButton = (
                    <div className={'cancelRollbackButton'}>
                        <Button onClick={() => this.handleStateAction('reverting_finished')} loading={this.state.actionButtonsLoading}>
                            Cancel Rollback
                        </Button>
                    </div>
                );
            }

            actionsInfo = (
                <div className="ProductListItem__Flex2 UpdateListItem__FlexButtons">
                    {pauseButton}
                    {cancelButton}
                    {resumeButton}
                    {restartButton}
                    {rollbackButton}
                    {pauseRollbackButton}
                    {cancelRollbackButton}
                    {resumeRollbackButton}
                    {restartRollbackButton}
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
                <div className="UpdateListItem__Main">
                    {dateInfo}
                    {descInfo}
                    {statusInfo}
                    {progressInfo}
                    {reportInfo}
                    {actionsInfo}
                </div>
            </ResourceItem>
        );
    }
}

import './UpdateListItem.css';
