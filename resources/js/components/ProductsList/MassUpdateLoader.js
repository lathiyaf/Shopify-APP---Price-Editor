import React, {Component} from 'react';
import {
    Button,
    Card,
    ProgressBar,
} from '@shopify/polaris';

let currentStateRequest = null;
export default class MassUpdateLoader extends Component {
    _isMounted = false;
    constructor(props) {
        super(props);
        this.state = {
            currentLoaders: [],
            displayedLoaders: [],
            updating: false,
            actionButtonsLoading: false
        }
    }

    componentDidUpdate(props) {
        const { refresh, id } = this.props;
        if (props.refresh !== refresh) {
            this.updateTimers();
        }
    }

    updateTimers() {
        $.ajax({
            url: '/massUpdateStatus',
            method: 'GET',
        }).then(
            result =>  {
                if(!this._isMounted) {
                    return
                }
                this.setState({
                    currentLoaders: result
                });
                if (result.length === 0) {
                    this.setState({
                        displayedLoaders: []
                    });
                    /// updateing finished
                    if(this.state.updating){
                        this.setState({
                            updating: false
                        });
                        this.props.onFinished();
                    }
                } else {
                    this.setState({
                        updating: true
                    });
                    this.updateProgressBars();
                }

            });
    }

    handleStateAction(newState, id, key) {

        this.setState({
            actionButtonsLoading: true
        });

        currentStateRequest = $.ajax({
            url: '/changeUpdateStatus',
            method: 'POST',
            data: {status: newState, id: id},
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
            }
        });
        currentStateRequest.then(data => {
            if(!this._isMounted) {
                return
            }
            let displayed = this.state.displayedLoaders;
            displayed[key]['status'] = 'running';
            this.setState({
                actionButtonsLoading: false,
                displayedLoaders: displayed
            });
            this.forceUpdate();
            this.props.onChangeState();

        });
    }

    updateProgressBars() {
        let current = this.state.currentLoaders;
        let displayed = [];
        current.map((item, index) => {
            displayed[index] = {
                id: item.id,
                percentage: item.updated / item.total * 100,
                updated: item.updated,
                total: item.total,
                variants: item.variants,
                status: item.status,
                updates_count: item.updates_count
            }
        });
        this.setState({
            displayedLoaders: displayed,
            currentLoaders: current,
        });
    }

    componentDidMount() {
        this._isMounted = true;
        this.intervalId = setInterval(this.updateTimers.bind(this), 5000);
    }

    componentWillUnmount() {
        clearInterval(this.intervalId);
        this._isMounted = false;
    }

    render() {
        const {displayedLoaders} = this.state;
        if (displayedLoaders.length === 0) {
            return <div/>
        }

        const {actionButtonsLoading} = this.state;

        let thisObject = this;

        return (
            <Card title="Updating prices">
                <Card.Section>
                    <div>
                    {
                        displayedLoaders.map(function (value, id) {

                            let errorInfo = '';
                            let restartButton = '';
                            let cancelButton = '';
                            let pauseButton = '';
                            let resumeButton = '';
                            if(value.status === 'failed'){
                                errorInfo =  (
                                    <div className={'updateFailWarning'}>There was an error updating all your products.
                                    Please click the "Restart" button to continue the update.</div>
                                );
                                restartButton = (
                                    <div className={'restartButton'}>
                                        <Button onClick={() => thisObject.handleStateAction('running', value.id, id)} loading={actionButtonsLoading}>
                                            Restart
                                        </Button>
                                    </div>
                                );
                                cancelButton = (
                                    <div className={'cancelButton'}>
                                        <Button onClick={() => thisObject.handleStateAction('finished', value.id, id)} loading={actionButtonsLoading}>
                                            Cancel
                                        </Button>
                                    </div>
                                );
                            }

                            if (value.status === 'running') {
                                pauseButton = (
                                    <div className={'pauseButton'}>
                                        <Button onClick={() => thisObject.handleStateAction('paused', value.id, id)} loading={actionButtonsLoading}>
                                            Pause
                                        </Button>
                                    </div>
                                );

                                cancelButton = (
                                    <div className={'cancelButton'}>
                                        <Button onClick={() => thisObject.handleStateAction('finished', value.id, id)} loading={actionButtonsLoading}>
                                            Cancel
                                        </Button>
                                    </div>
                                );
                            }

                            if (value.status === 'paused') {
                                resumeButton = (
                                    <div className={'resumeButton'}>
                                        <Button onClick={() => thisObject.handleStateAction('running', value.id, id)} loading={actionButtonsLoading}>
                                            Resume
                                        </Button>
                                    </div>
                                );
                            }

                            let item_type = 'variants';
                            if(!value.variants){
                                item_type = 'products';
                            }

                            let update_text = (
                                <div>{value.updated}/{value.total} variants</div>
                            );
                            if(!value.variants){
                                update_text = (
                                    <div>{value.updates_count} variants in {value.updated}/{value.total} products</div>
                                );
                            }


                            return (
                                <div key={value.id}>
                                    <div>
                                        <ProgressBar key={id} id={value.id} size="medium" progress={value.percentage}/>
                                        {update_text}
                                    </div>
                                    {errorInfo}
                                    <div id={"massUpdateStatusButtonsWrap"}>
                                        {restartButton}
                                        {cancelButton}
                                        {resumeButton}
                                        {pauseButton}
                                    </div>
                                </div>
                            )
                        })
                    }
                    </div>
                </Card.Section>
            </Card>
        )
    }

}
