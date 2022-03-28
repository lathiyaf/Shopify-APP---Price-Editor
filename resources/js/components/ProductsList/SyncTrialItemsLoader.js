import React, {Component} from 'react';
import {
    Banner,
    Stack,
    ProgressBar,
} from '@shopify/polaris';

export default class SyncTrialItemsLoader extends Component {
    _isMounted = false;
    constructor(props) {
        super(props);
        this.state = {
            currentLoader: [],
            displayedLoader: [],
        }
    }

    updateTimers() {
        fetch("/syncTrialItemsStatus", {
            headers: new Headers({
                'Authorization': 'Bearer '+window.sessionToken,
                'X-Requested-With': 'XMLHttpRequest'
            }),
        })
            .then(res => res.json())
            .then(
                (result) => {
                    if(!this._isMounted) {
                        return
                    }
                    this.setState({
                        currentLoader: result
                    });
                    if (result.length === 0) {
                        this.setState({
                            displayedLoader: []
                        });
                    } else {
                        this.updateProgressBars();
                    }

                },
                (error) => {
                    this.setState({
                        error
                    });
                }
            )
    }

    updateProgressBars() {
        let current = this.state.currentLoader;
        let displayed = this.state.displayedLoader;
        displayed = {
            percentage: current.used / current.total * 100,
            used: current.used > current.total ? current.total : current.used,
            total: current.total
        };
        this.setState({
            displayedLoader: displayed,
            currentLoader: current,
        });

    }

    componentDidMount() {
        this._isMounted = true;
        this.updateTimers.bind(this);
        this.intervalId = setInterval(this.updateTimers.bind(this), 5000);
    }

    componentWillUnmount() {
        clearInterval(this.intervalId);
        this._isMounted = false;
    }

    render() {
        const {displayedLoader} = this.state;
        if (displayedLoader.length === 0) {
            return <div/>
        }
        let title = "Apply price updates to "+SHOPIFY_TRIAL_MODE_LIMIT+" variants for free!";
        return (

            <div id={"trialInfoBanner"}>
                <Banner
                    title={title}
                    status="info"
                >
                    <p>
                        As part of your free trial you can try out our app and update up to {SHOPIFY_TRIAL_MODE_LIMIT} variants for free.
                    </p>
                    <p>
                        The basic plan will apply automatically when your balance is used.
                    </p>
                    <div style={{marginTop: '1rem'}}>
                        <Stack distribution="equalSpacing">
                            <span>Balance</span>
                            <span>You used {displayedLoader.used} of {displayedLoader.total} free variants</span>
                        </Stack>
                        <ProgressBar size="medium" progress={displayedLoader.percentage}/>
                    </div>
                </Banner>
            </div>
        )
    }

}
