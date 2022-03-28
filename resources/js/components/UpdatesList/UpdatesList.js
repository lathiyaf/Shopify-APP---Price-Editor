import React, {Component} from 'react';
import {
    Card,
    ResourceList,
    Pagination,
} from '@shopify/polaris';
import '@shopify/polaris/build/esm/styles.css';


import UpdateListItem from './UpdateListItem';
import IndexPagination from '../IndexPagination/IndexPagination';

const resourceName = {
    singular: 'item',
    plural: 'items',
};





const updates = {
    "updates": [
        {
            "id": 0,
            "created_datetime": "",
            "description": "",
            "status": "",
            "status_text": "",
            "updated": 0,
            "total": 0,
            "file_name": '',
            "errors": [],
            "file_url": '',
            "updates_count": 0,
            "updated_products_count": 0,
            "reverted_updates_count": 0
        }
    ]
};

let currentRequest = null;


export default class UpdatesList extends Component {
    _isMounted = false;
    constructor(props) {
        super(props);
        let {
            isMainPage
        } = props;

        if(isNaN(isMainPage)){
            isMainPage = 0;
        }

        this.state = {
            items: updates.updates,
            searchValue: '',
            isFirstPage: true,
            isLastPage: false,
            shopDomain: '',
            shopInfo: [],
            currentPage: 1,
            countItems: 0,
            countPages: 0,
            loading: true,
            isTrial: SHOPIFY_IS_TRIAL,
            trialItemsUsed: SHOPIFY_TRIAL_ITEMS_USED,
            trialModeLimit: SHOPIFY_TRIAL_MODE_LIMIT,
            isMainPage: parseInt(isMainPage),
            refreshChildren: false,
        };



        this.fetchUpdates = this.fetchUpdates.bind(this);
        this.handlePreviousPage = this.handlePreviousPage.bind(this);
        this.handlePreviousPage = this.handlePreviousPage.bind(this);
        this.handleNextPage = this.handleNextPage.bind(this);
        this.handleChangeItemState= this.handleChangeItemState.bind(this);
        this.handleRefreshChildren= this.handleRefreshChildren.bind(this);
    }

    fetchUpdates(options){
        options = options || {};
        let page = 1;
        if(options.hasOwnProperty("page")) {
            page = options.page;
        }


        if(currentRequest != null) {
            currentRequest.abort();
        }


        this.setState({
            loading: true
        });

        currentRequest = $.ajax({
            url: '/getUpdates',
            method: 'GET',
            data: { product_list_page: page, isMainPage: this.state.isMainPage},
        });


        currentRequest.then(data => {
            // this.setState({ items: result });
            if(!this._isMounted) {
                return
            }
            let isFirstPage = false;
            let isLastPage = false;
            if(page === 1){
                isFirstPage = true;
            }

            if(page === data.count_pages){
                isLastPage = true;
            }

            currentRequest = null;
            this.setState({items: {}});

            this.setState({
                items: data.updates,
                isFirstPage: isFirstPage,
                isLastPage: isLastPage,
                currentPage: page,
                loading:false,
                shopDomain: data.domain,
                shopInfo: data.shopInfo,
                countItems: data.count,
                countPages: data.count_pages,
            });
        });

    }


    componentDidUpdate(props) {
        const { refresh, id } = this.props;
        if (props.refresh !== refresh) {
            this.fetchUpdates();
        }
    }



    componentDidMount() {
        this._isMounted = true;
        $.ajax({
            method:'get',
            url: "/getShopInfo",
        }).done((res) => {
            this.setState({
                isTrial:res.isTrial,
                trialModeLimit: res.trial_mode_limit,
                trialItemsUsed: res.trial_items_used,
                initialShopInfo: 1
            });
            this.fetchUpdates();
        });


    }

    render() {
        const {
            items,
            isFirstPage,
            isLastPage,
            shopDomain,
            shopInfo,
            currentPage,
            countItems,
            isTrial,
            trialModeLimit,
            trialItemsUsed
        } = this.state;

        let paginationMarkup = '';
        if(!this.state.isMainPage){
            paginationMarkup = items.length > 0
                ? (
                    <IndexPagination>
                        <Pagination
                            hasPrevious={!isFirstPage}
                            hasNext={!isLastPage}
                            onPrevious={this.handlePreviousPage}
                            onNext={this.handleNextPage}
                        />
                    </IndexPagination>
                )
                : null;
        }


        return (
                <div id={"updates_content"}>
                    <Card>
                        <div id={"Polaris-Card-Updates-List"} className={"Polaris-Card"}>
                            {paginationMarkup}
                            <Card>
                                <ResourceList
                                    resourceName={resourceName}
                                    items={items}
                                    renderItem={(update) => { return <UpdateListItem onChangeItemState={this.handleChangeItemState} onRefreshChildren={this.handleRefreshChildren} shopInfo={shopInfo} shopDomain={shopDomain} {...update} />} }
                                    loading={this.state.loading}
                                    hasMoreItems
                                />


                                {paginationMarkup}
                            </Card>
                        </div>
                    </Card>
                </div>
        );
    }

    handlePreviousPage() {
        let prevPage = this.state.currentPage - 1;
        let searchValue = this.state.searchValue;
        let appliedFilters = this.state.appliedFilters;
        this.fetchUpdates({page: prevPage, title: searchValue, appliedFilters: appliedFilters});
    }

    handleNextPage() {
        let nextPage = this.state.currentPage + 1;
        let searchValue = this.state.searchValue;
        let appliedFilters = this.state.appliedFilters;
        this.fetchUpdates({page: nextPage, title: searchValue, appliedFilters: appliedFilters});

    }


    handleUpdate(){
        this.fetchUpdates({page: this.state.currentPage});
    }


    handleChangeItemState() {
        this.props.onChangeState();
    }

    handleRefreshChildren() {
        this.setState({
            refreshChildren: !this.state.refreshChildren,
        });
    }

    componentWillUnmount() {
        this._isMounted = false;
    }

}
