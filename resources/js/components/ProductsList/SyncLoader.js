import React, {Component} from 'react';
import {
    Card,
    ProgressBar,
} from '@shopify/polaris';

export default class SyncLoader extends Component {
    _isMounted = false;
    constructor(props) {
        super(props);
        this.state = {
            currentLoaders: [],
            displayedLoaders: [],
        }
    }

    updateTimers() {
        fetch("/syncStatus", {
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
                        currentLoaders: result
                    });
                    if (result.length === 0) {
                        this.setState({
                            displayedLoaders: []
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
        let current = this.state.currentLoaders;
        let displayed = this.state.displayedLoaders;
        current.map((item, index) => {
            displayed[index] = {
                id: item.id,
                percentage: item.updated / item.total * 100,
                updated: item.updated,
                total: item.total
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
        return (
            <Card title="Product and collection data is syncing">
                <Card.Section>
                    <div>
                    {
                        displayedLoaders.map(function (value, id) {
                            return (
                                <div>
                                    <ProgressBar key={id} id={value.id} size="medium" progress={value.percentage}/>
                                    <div>{value.updated}/{value.total}</div>
                                </div>
                            );
                        })
                    }
                    </div>
                </Card.Section>
            </Card>
        )
    }

}
