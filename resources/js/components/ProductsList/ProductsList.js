import React, {Component} from 'react';
import TimePicker from 'react-time-picker';
import 'react-calendar/dist/Calendar.css';
import DatePicker from 'react-date-picker';

import {
    Card,
    ResourceList,
    Filters,
    Pagination,
    FormLayout,
    TextField,
    Checkbox,
    Form,
    Select,
    Button,
    Link,
    Icon,
    ChoiceList,
    Sticky, Stack, TextStyle, Banner, Modal
} from '@shopify/polaris';
import '@shopify/polaris/build/esm/styles.css';

import MassUpdateLoader from './MassUpdateLoader';
import SyncLoader from './SyncLoader';
import SyncTrialItemsLoader from './SyncTrialItemsLoader';
import ProductListItem from './ProductListItem';
import IndexPagination from '../IndexPagination/IndexPagination';
import UpdatesList from './../UpdatesList';
import ScheduledUpdatesList from "../ScheduledUpdatesList";

import {
    CalendarMajor
} from '@shopify/polaris-icons';

const resourceName = {
    singular: 'variant',
    plural: 'variants',
};





const products = {
    "products": [
        {
            "id": 0,
            "title": "",
            "vendor": "",
            "product_type": ""
        }
    ]
};

let currentRequest = null;
let currentSyncRequest = null;
let typingTimer = null;


export default class ProductsList extends Component {
    _isMounted = false;
    constructor(props) {
        super(props);

        const {
            historyTab,
            schedulingTab
        } = props;

        let curDate = new Date();
        let tomorrowDate = new Date(new Date().getTime() + 24 * 60 * 60 * 1000);
        var curTime = curDate.getHours()+":"+(curDate.getMinutes()<10?'0':'') + curDate.getMinutes();
        var tomorrowTime = tomorrowDate.getHours()+":"+(tomorrowDate.getMinutes()<10?'0':'') + tomorrowDate.getMinutes();

        this.state = {
            items: products.products,
            selectedItems: [],
            sortValue: 'DATE_MODIFIED_DESC',
            appliedFilters: [],
            availableFilters: [],
            searchValue: '',
            isFirstPage: true,
            isLastPage: false,
            shopDomain: '',
            shopInfo: [],
            currentPage: 1,
            countItems: 0,
            countPages: 0,
            loading: true,
            updatePriceSelect: 'discount',
            updatePriceAmount: '',
            updatePriceCheckbox: false,
            updatePriceActionType: 'by',
            priceRoundingCheckbox: false,
            roundToNearestValueCheckbox: false,
            schedulingCheckbox: false,
            schedulingType: 'period',
            schedulingStartDate: curDate,
            schedulingEndDate: tomorrowDate,
            schedulingStartTime: curTime,
            schedulingEndTime: tomorrowTime,
            schedulingEndDateCheckbox: false,
            schedulingStartDay: '1',
            schedulingEndDay: '2',
            isSchedulingDateError: false,
            isSchedulingTimeError: false,
            startCalendarActive: false,
            endCalendarActive: false,
            updatePriceRoundStep: 0.99,
            isTrial: SHOPIFY_IS_TRIAL,
            trialItemsUsed: SHOPIFY_TRIAL_ITEMS_USED,
            trialModeLimit: SHOPIFY_TRIAL_MODE_LIMIT,
            updatesCount: SHOPIFY_UPDATES_COUNT,
            showUpdateNoticeModal: false,
            historyTab: historyTab,
            schedulingTab: schedulingTab,
            refreshUpdates: 0,
            refreshLoader: 0,
            refreshScheduling: 0,
            cursors: [],
            nextPageInfo: '',
            previousPageInfo: '',
            currentPageInfo: '',
            availableCollections: []
        };



        this.fetchProducts = this.fetchProducts.bind(this);
        this.handleSelectionChange = this.handleSelectionChange.bind(this);
        this.handleProductTypeFilterChange = this.handleProductTypeFilterChange.bind(this);
        this.handleVendorFilterChange = this.handleVendorFilterChange.bind(this);
        this.handleCollectionFilterChange = this.handleCollectionFilterChange.bind(this);
        this.handleTagFilterChange = this.handleTagFilterChange.bind(this);
        this.handleClearAll = this.handleClearAll.bind(this);
        this.handleFilterRemove = this.handleFilterRemove.bind(this);
        this.handleSearchChange = this.handleSearchChange.bind(this);
        this.handleSearchClear = this.handleSearchClear.bind(this);
        this.handlePreviousPage = this.handlePreviousPage.bind(this);
        this.handleSaveFilters = this.handleSaveFilters.bind(this);
        this.handlePreviousPage = this.handlePreviousPage.bind(this);
        this.handleNextPage = this.handleNextPage.bind(this);
        this.handleBulkEdit = this.handleBulkEdit.bind(this);
        this.handleBulkUpdatePrices = this.handleBulkUpdatePrices.bind(this);
        this.handleUpdate = this.handleUpdate.bind(this);
        this.handleUpdateScheduling = this.handleUpdateScheduling.bind(this);
        this.handleUpdates = this.handleUpdates.bind(this);
        this.handleUpdatesAndScheduling = this.handleUpdatesAndScheduling.bind(this);
        this.handleToggleRounding = this.handleToggleRounding.bind(this);
        this.handleToggleScheduling = this.handleToggleScheduling.bind(this);
        this.handleSchedulingTypeChange = this.handleSchedulingTypeChange.bind(this);
        this.handleStartCalendarActive = this.handleStartCalendarActive.bind(this);
        this.handleStartCalendarInActive = this.handleStartCalendarInActive.bind(this);
        this.handleEndCalendarActive = this.handleEndCalendarActive.bind(this);
        this.handleEndCalendarInActive = this.handleEndCalendarInActive.bind(this);
        this.handleChangeSchedulingStartDate = this.handleChangeSchedulingStartDate.bind(this);
        this.handleChangeSchedulingEndDate = this.handleChangeSchedulingEndDate.bind(this);
        this.handleChangeSchedulingStartTime = this.handleChangeSchedulingStartTime.bind(this);
        this.handleChangeSchedulingEndTime = this.handleChangeSchedulingEndTime.bind(this);
        this.handleChangeSchedulingStartDay = this.handleChangeSchedulingStartDay.bind(this);
        this.handleChangeSchedulingEndDay = this.handleChangeSchedulingEndDay.bind(this);
        this.handleToggleUpdateNotice = this.handleToggleUpdateNotice.bind(this);
        this.handleUpdateConfirmed = this.handleUpdateConfirmed.bind(this);
        this.handlePriceSelectChange = this.handlePriceSelectChange.bind(this);
    }

    fetchProducts(options){
        options = options || {};
        let page = 1;
        let paginationType = 'current';
        let title = null;
        let appliedFilters = {};
        let resetPagination = false;
        if(options.hasOwnProperty("page")) {
            page = options.page;
        }
        if(options.hasOwnProperty("title")) {
            title = options.title;
        }

        if(options.hasOwnProperty("paginationType")) {
            paginationType = options.paginationType;
        }

        if(options.hasOwnProperty("appliedFilters")) {
            appliedFilters = options.appliedFilters;
        } else {
            appliedFilters = [];
        }
        if(options.hasOwnProperty("resetPagination")) {
            resetPagination = options.resetPagination;
        }

        if(currentRequest != null) {
            currentRequest.abort();
        }

        let cursors = this.state.cursors;
        let currentPageInfo = this.state.currentPageInfo;
        let nextPageInfo = this.state.nextPageInfo;
        let previousPageInfo = this.state.previousPageInfo;
        if(resetPagination){
            cursors = [];
            currentPageInfo = nextPageInfo = previousPageInfo = "";
        }

        this.setState({
            loading: true
        });




        currentRequest = $.ajax({
            url: '/getProducts',
            method: 'GET',
            data: {
                product_list_page: page,
                product_list_title: title,
                appliedFilters: appliedFilters,
                cursors: cursors,
                current_page_info: currentPageInfo,
                next_page_info: nextPageInfo,
                previous_page_info: previousPageInfo,
                pagination_type: paginationType
            },
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

            let productTypeValue = ''
            let vendorValue = ''
            let collectionValue = ''
            let tagValue = ''
            appliedFilters.forEach(function(item) {
                if(item.key === 'productTypeFilter'){
                    productTypeValue = item.value;
                } else if (item.key === 'vendorFilter') {
                    vendorValue = item.value;
                } else if (item.key === 'collectionFilter') {
                    collectionValue = item.value;
                } else if (item.key === 'tagFilter') {
                    tagValue = item.value;
                }
            });

            let collectionsOptions = [{label: "Select a filter", value: ""}];
            Object.keys(data.collections).forEach(key => {
                collectionsOptions.push({
                    label: data.collections[key], value: key
                })
            });

            data.product_types.unshift({label: "Select a filter", value: ""})
            data.vendors.unshift({label: "Select a filter", value: ""})
            data.tags.unshift({label: "Select a filter", value: ""})

            let availableFilters = [
                {
                    key: 'productTypeFilter',
                    label: 'Product Type',
                    filter: (
                        <Select
                            label={""}
                            labelHidden={true}
                            options={data.product_types}
                            onChange={this.handleProductTypeFilterChange}
                            value={productTypeValue}
                        />
                    ),
                    shortcut: true,
                },
                {
                    key: 'vendorFilter',
                    label: 'Vendor',
                    filter: (
                        <Select
                            label={""}
                            labelHidden={true}
                            options={data.vendors}
                            onChange={this.handleVendorFilterChange}
                            value={vendorValue}
                        />
                    ),
                    shortcut: true,
                },
                {
                    key: 'collectionFilter',
                    label: 'Collection',
                    filter: (
                        <Select
                            label={""}
                            labelHidden={true}
                            options={collectionsOptions}
                            onChange={this.handleCollectionFilterChange}
                            value={collectionValue}
                        />
                    ),
                    shortcut: true,
                },
                {
                    key: 'tagFilter',
                    label: 'Tag',
                    filter: (
                        <Select
                            label={""}
                            labelHidden={true}
                            options={data.tags}
                            onChange={this.handleTagFilterChange}
                            value={tagValue}
                        />
                    ),
                    shortcut: true,
                }
            ];

            this.setState({
                items: data.products,
                isFirstPage: isFirstPage,
                isLastPage: isLastPage,
                currentPage: page,
                loading:false,
                shopDomain: data.domain,
                shopInfo: data.shopInfo,
                countItems: data.count,
                countPages: data.count_pages,
                cursors: data.cursors,
                previousPageInfo: data.previous_page_info,
                nextPageInfo: data.next_page_info,
                currentPageInfo: data.current_page_info,
                searchValue: title,
                // appliedFilters: appliedFilters,
                availableFilters: availableFilters,
                isUpdatePriceButtonDisabled: false,
                isSyncButtonDisabled: false,
                availableCollections: data.collections
            });
        });

    }


    updatePrices(options) {
        if (this.state.selectedItems.length === 0) {
            alert('Select variants at first, please');
            return;
        }
        if((this.state.updatePriceSelect === 'update' || this.state.updatePriceSelect === 'discount'
            || ( this.state.updatePriceSelect === 'update_compare_at_price' && this.state.updatePriceActionType === 'by' )
            || this.state.updatePriceSelect === 'update_based_on_compare_at_price'
            || this.state.updatePriceSelect === 'update_based_on_price'
            )
            && (parseFloat(this.state.updatePriceAmount) === 0 || isNaN(parseFloat(this.state.updatePriceAmount)))
        ){
            alert("Amount can't be empty");
            return;
        }
        if(this.state.updatePriceSelect === 'update_based_on_compare_at_price'
            && parseFloat(this.state.updatePriceAmount) > 0) {
            alert("Compare at Price must be higher then price!");
            return;
        }
        if(this.state.updatePriceSelect === 'update_based_on_price'
            && parseFloat(this.state.updatePriceAmount) < 0) {
            alert("Compare at Price must be higher then price!");
            return;
        }
        this.setState({
            loading: true
        });
        options.update_price_product_ids = this.state.selectedItems;
        // options.update_price_product_ids = 'all';
        options.update_price_search_title = this.state.searchValue;

        let update_price_filters = [];
        this.state.appliedFilters.forEach(function(item) {
            update_price_filters.push({
                key: item.key,
                value: item.value
            })
        });
        options.update_price_filters = update_price_filters;

        this.setState({ isUpdatePriceButtonDisabled: true});

        currentRequest = $.ajax({
            url: '/updatePrices',
            method: 'POST',
            data: options,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
            },
        });
        currentRequest.then(data => {
            currentRequest = null;
            this.fetchProducts({page: this.state.currentPage, title: this.state.searchValue, appliedFilters: this.state.appliedFilters});
            this.setState({
                isUpdatePriceButtonDisabled: false,
                isTrial:data.isTrial,
                trialModeLimit: data.trial_mode_limit,
                trialItemsUsed: data.trial_items_used,
                selectedItems: [],
                updatesCount: this.state.updatesCount + 1,
            });
            this.setState({refreshUpdates: !this.state.refreshUpdates})
            this.setState({refreshLoader: !this.state.refreshLoader})
            this.setState({refreshScheduling: !this.state.refreshScheduling})
            if(this.state.schedulingCheckbox) {
                $('#scheduled_updates-tab').click();
            }
        });
    }


    syncTypesAndVendors(options) {

        this.setState({
            loading: true
        });

        this.setState({ isSyncButtonDisabled: true});

        currentSyncRequest = $.ajax({
            url: '/syncTypesAndVendors',
            method: 'POST',
            data: options,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
            }
        });
        currentSyncRequest.then(data => {
            this.setState({ isSyncButtonDisabled: false, loading: false});
            var url = location.href;               //Save down the URL without hash.
            location.href = "#Polaris-Card-Syncing-Status";                 //Go to the target element.
            history.replaceState(null,null,location.href);
            // SyncLoader.forceUpdate();


        });
    }



    handlePriceSubmit = (event) => {
        if (event === undefined ||
            (this.state.schedulingCheckbox && this.state.schedulingEndDateCheckbox &&
                (this.state.isSchedulingDateError || this.state.isSchedulingTimeError))) {
            return;
        }
        if(this.state.updatesCount === 0 && event !== "confirm") {
            this.setState({ showUpdateNoticeModal: true});
            return;
        }
        // if(event !== "confirm"
        //     // && !confirm('Are you sure?')
        // ){
        //     return;
        // }
        let data = {
            update_price_subtype: this.state.updatePriceSelect,
            update_price_value: this.state.updatePriceAmount,
            update_price_action_type: this.state.updatePriceActionType
        };
        if(this.state.updatePriceSelect === 'update' || this.state.updatePriceSelect === 'update_with_compare_at_price'
            || this.state.updatePriceSelect === 'update_price_with_cost' || this.state.updatePriceSelect === 'discount'
            || this.state.updatePriceSelect === 'update_based_on_compare_at_price'
            ){
            data.apply_to_compare_at_price = this.state.updatePriceCheckbox ? 1 : 0;
            data.update_price_type = 'price';
        } else {
            data.apply_to_price = this.state.updatePriceCheckbox ? 1 : 0;
            data.update_price_type = 'compare_at_price';
        }

        if(this.state.priceRoundingCheckbox){
            data.update_price_round_step = this.state.updatePriceRoundStep;
        }

        data.round_to_nearest_value = this.state.roundToNearestValueCheckbox ? 1 : 0;
        if(this.state.schedulingCheckbox){
            data.is_scheduling = 1;
            data.scheduling_type = this.state.schedulingType;
            if(data.scheduling_type === 'period') {
                let sd = this.state.schedulingStartDate;
                data.scheduling_start_date = sd.getFullYear() + "-" + ("0"+(sd.getMonth()+1)).slice(-2)
                    + "-" + ("0" + sd.getDate()).slice(-2);
                data.scheduling_start_time = this.state.schedulingStartTime;
                if(this.state.schedulingEndDateCheckbox) {
                    data.scheduling_has_end_date = 1;
                    let ed = this.state.schedulingEndDate;
                    data.scheduling_end_date = ed.getFullYear() + "-" + ("0"+(ed.getMonth()+1)).slice(-2)
                        + "-" + ("0" + ed.getDate()).slice(-2);
                    data.scheduling_end_time = this.state.schedulingEndTime;
                }
            } else {
                data.scheduling_start_day = this.state.schedulingStartDay;
                data.scheduling_end_day = this.state.schedulingEndDay;
                data.scheduling_start_time = this.state.schedulingStartTime;
                if(data.scheduling_type === 'daily') {
                    if(this.state.schedulingEndDateCheckbox) {
                        data.scheduling_has_end_date = 1;
                        data.scheduling_end_time = this.state.schedulingEndTime;
                    }
                } else {
                    data.scheduling_end_time = this.state.schedulingEndTime;
                }

            }
        }

        return this.updatePrices(data)
    };


    handleTypesAndVendorsSubmit = (event) => {
        if (event === undefined) {
            return;
        }
        return this.syncTypesAndVendors()
    };

    handleChange = (field) => {
        if(field === 'updatePriceSelect' && this.state.updatePriceSelect !== 'update'
            && this.state.updatePriceSelect !== 'update_compare_at_price') {
            return (value) => this.setState({[field]: value, updatePriceActionType: 'by'});
        }
        return (value) => this.setState({[field]: value});
    };

    handlePriceSelectChange(value) {
        this.setState({ updatePriceSelect: value });
        if(value !== 'update' && value !== 'update_compare_at_price') {
            this.setState({
                updatePriceActionType: 'by',
            });
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
                initialShopInfo: 1,
                updatesCount: res.updates_count
            });
            this.fetchProducts();
        });
    }

    componentDidUpdate() {
        let selectedItems = this.state.selectedItems;
        let countItems = this.state.countItems;

        if(selectedItems.length === 0) {
            return
        }
        let selectAll = false;
        $('.Polaris-BulkActions__PaginatedSelectAll').each(function(){
            if($(this).find("span[aria-live='polite']").length === 0){
                $(this).find('.Polaris-Button__Content').each(function(){
                    $(this).find("span").text("Select all "+countItems+" products in your store");
                });
            } else {
                selectAll= true;
                $(this).find('.Polaris-Button__Content').each(function(){
                    $(this).find("span").text("Clear selection");
                });
                $(this).find("span[aria-live='polite']").first().text("All items are selected");
            }
        });

        if(!selectAll){
            $('.Polaris-ResourceList__BulkActionsWrapper').find('.Polaris-CheckableButton__Label').each(function(){
                $(this).text(selectedItems.length+' variants selected');
            });
        } else {
            $('.Polaris-ResourceList__BulkActionsWrapper').find('.Polaris-CheckableButton__Label').each(function(){
                $(this).text('All selected');
            });
        }
    }

    render() {
        const {
            items,
            selectedItems,
            sortValue,
            appliedFilters,
            availableFilters,
            searchValue,
            isFirstPage,
            isLastPage,
            shopDomain,
            shopInfo,
            currentPage,
            countItems,
            isTrial,
            trialModeLimit,
            trialItemsUsed,
            showUpdateNoticeModal
        } = this.state;



        const submitPriceButton =
            (isTrial === 1 && trialItemsUsed >= trialModeLimit)
            ? (
                <div className={'submitButtonUpgrade'}>
                    <Link id={'upgradeNowBtn1'} url="/billing">Upgrade now</Link>
                </div>
            )
            :
            (
                <div className={'submitButton'}>
                    <Button loading={this.state.isUpdatePriceButtonDisabled} submit disabled={this.state.isUpdatePriceButtonDisabled}>Submit</Button>
                </div>
            );



        let payingNotice = null;

        if(isTrial){
            if(trialItemsUsed >= trialModeLimit){
                payingNotice = (
                    <Card>
                        <div id={'payingNoticeDiv'}>
                            <span>You have reached your {trialModeLimit} updates limit.</span>
                            <a href="/billing" className="btn btn-custom" id="upgrade_plan_btn_products">Upgrade now</a>
                        </div>
                    </Card>
                )
            } else {
                payingNotice = (
                    <Card>
                        <div id={'payingNoticeDiv'}>
                            <span>Your account is currently in Trial Mode and is limited to {trialModeLimit} price updates.</span>
                            <a href="/billing" className="btn btn-custom" id="upgrade_plan_btn_products">Upgrade now</a>
                        </div>
                    </Card>
                )
            }
        }


        const paginationMarkup = items.length > 0
            ? (
                <div id={'headerPaginationWrap'}>
                    <div className={"syncProductsWrap"}>
                        <sup><i className="fa fa-info-circle" title="Sync the latest product and collection data for use in filtering."></i></sup>
                        <Button onClick={this.handleTypesAndVendorsSubmit}  disabled={this.state.isSyncButtonDisabled} loading={this.state.isSyncButtonDisabled}>Sync</Button>
                    </div>
                    <div className={"productTitleWrap"}>
                        <h3 className={"Polaris-Subheading"}>Products</h3>
                    </div>
                    <IndexPagination>
                        <Pagination
                            hasPrevious={!isFirstPage}
                            hasNext={!isLastPage}
                            onPrevious={this.handlePreviousPage}
                            onNext={this.handleNextPage}
                        />
                    </IndexPagination>
                </div>

            )
            : null;

        const paginationMarkupDown = items.length > 0
            ? (
                <div id={'headerPaginationWrapDown'}>
                    <IndexPagination>
                        <Pagination
                            hasPrevious={!isFirstPage}
                            hasNext={!isLastPage}
                            onPrevious={this.handlePreviousPage}
                            onNext={this.handleNextPage}
                        />
                    </IndexPagination>
                </div>

            )
            : null;

        const actionsMarkup = () => {
            if (this.state.items.length === undefined || this.state.items.length < 1) {
                return;
            }
            const {updatePriceSelect, updatePriceAmount, updatePriceCheckbox,
                updatePriceActionType, priceRoundingCheckbox, roundToNearestValueCheckbox,
                updatePriceRoundStep, schedulingCheckbox, schedulingType, schedulingStartDate, schedulingEndDate,
                schedulingStartTime, schedulingEndTime, startCalendarActive, endCalendarActive,
                schedulingEndDateCheckbox, schedulingStartDay, schedulingEndDay, isSchedulingTimeError,
                isSchedulingDateError} = this.state;
            let shouldBeDisabledPrice =
                (updatePriceSelect === 'update_with_compare_at_price' || updatePriceSelect === 'update_with_price');
            let shouldBeDisabledCheckbox =
                (updatePriceSelect === 'update_with_compare_at_price' || updatePriceSelect === 'update_with_price'
                    || updatePriceSelect === 'update_price_with_cost' || updatePriceSelect === 'update_compare_at_price_with_cost'
                    || updatePriceSelect === 'update_based_on_compare_at_price'
                    || updatePriceSelect === 'update_based_on_price'
                || updatePriceSelect === 'discount' );

            let shouldBeDisabledPriceRounding = roundToNearestValueCheckbox == true;
            let updatePriceCheckboxlabel =
                (updatePriceSelect === 'update' || updatePriceSelect === 'update_with_compare_at_price')
                ? "Apply same to 'Compare At Price'" : "Apply same to 'Price'";


            let updatePricePlaceholder =
                (updatePriceSelect === 'discount' || updatePriceSelect === 'update_based_on_price') ? "e.g. 10%, 10" :
                (updatePriceSelect === "update_based_on_compare_at_price") ? "e.g. -10%, -10" :
                (updatePriceActionType === "to" && updatePriceSelect !== "update_based_on_compare_at_price"
                    && updatePriceSelect !== "update_price_with_cost"
                    && updatePriceSelect !== "update_compare_at_price_with_cost"
                )
                    ? "e.g. 99" : "e.g. 10%, -10%, +10, -10";

            let originalPrice = 25;
            let discount = 15;
            let discountedPrice = 21.25;
            let roundedPrice = 21.25;
            if(roundToNearestValueCheckbox){
                roundedPrice = 21;
            } else {
                roundedPrice = 21 + parseFloat(updatePriceRoundStep);
            }
            let schedulingHtml = '';
            if(SHOPIFY_IS_PRO){

                let schedulingOptions = '';
                let schedulingEndTimeError = '';
                let schedulingFieldClass = 'Polaris-TextField';

                if(isSchedulingTimeError) {
                    schedulingEndTimeError = (
                        <div className="Polaris-Labelled__Error">
                            <div className="Polaris-InlineError">
                                <div className="Polaris-InlineError__Icon"><span className="Polaris-Icon"><svg
                                    className="Polaris-Icon__Svg" viewBox="0 0 20 20" focusable="false" aria-hidden="true"><path
                                    d="M10 18a8 8 0 1 1 0-16 8 8 0 0 1 0 16zm-1-8h2V6H9v4zm0 4h2v-2H9v2z"></path></svg></span></div>
                                End time should be higher then start time
                            </div>
                        </div>
                    );
                    schedulingFieldClass += " Polaris-TextField--error";
                }
                const schedulingStartTimeIconContent = () => {
                    return (
                        <svg className="Polaris-Icon__Svg" viewBox="0 0 612 612" focusable="false" aria-hidden="true"><g><path d="M587.572,186.881c-32.266-75.225-87.096-129.934-162.949-162.285C386.711,8.427,346.992,0.168,305.497,0.168c-41.488,0-80.914,8.181-118.784,24.428C111.488,56.861,56.415,111.535,24.092,186.881C7.895,224.629,0,264.176,0,305.664c0,41.496,7.895,81.371,24.092,119.127c32.323,75.346,87.403,130.348,162.621,162.621c37.877,16.247,77.295,24.42,118.784,24.42c41.489,0,81.214-8.259,119.12-24.42c75.853-32.352,130.683-87.403,162.956-162.621C603.819,386.914,612,347.16,612,305.664C612,264.176,603.826,224.757,587.572,186.881z M538.724,440.853c-24.021,41.195-56.929,73.876-98.375,98.039c-41.195,24.021-86.332,36.135-134.845,36.135c-36.47,0-71.27-7.024-104.4-21.415c-33.129-14.384-61.733-33.294-85.661-57.215c-23.928-23.928-42.973-52.811-57.214-85.997c-14.199-33.065-21.08-68.258-21.08-104.735c0-48.52,11.921-93.428,35.807-134.509c23.971-41.231,56.886-73.947,98.039-98.04c41.146-24.092,85.99-36.142,134.502-36.142c48.52,0,93.649,12.121,134.845,36.142c41.446,24.164,74.283,56.879,98.375,98.039c24.092,41.153,36.135,85.99,36.135,134.509C574.852,354.185,562.888,399.399,538.724,440.853z"></path><path d="M324.906,302.988V129.659c0-10.372-9.037-18.738-19.41-18.738c-9.701,0-18.403,8.366-18.403,18.738v176.005c0,0.336,0.671,1.678,0.671,2.678c-0.671,6.024,1.007,11.043,5.019,15.062l100.053,100.046c6.695,6.695,19.073,6.695,25.763,0c7.694-7.695,7.188-18.86,0-26.099L324.906,302.988z"></path></g></svg>
                    );
                };
                let schedulingStartTimeHtml = (
                    <div className={"col-lg-6 col-md-12 col-sm-12 col-xs-12"}>
                        <Stack>
                            <Stack.Item fill>
                                <TextStyle variation="strong">Start time</TextStyle>
                            </Stack.Item>
                        </Stack>
                        <div className={"Polaris-TextField"}>
                            <div className={"Polaris-TextField__Input SchedulingCalendarWrap"}>
                                <Stack>
                                    <Stack.Item>
                                        <Icon source={schedulingStartTimeIconContent} />
                                    </Stack.Item>
                                    <Stack.Item>
                                        <TimePicker
                                            clearIcon={null}
                                            clockIcon={null}
                                            value={schedulingStartTime}
                                            onChange={this.handleChangeSchedulingStartTime}
                                            format={"h:mm a"}
                                            disableClock={true}
                                        />
                                    </Stack.Item>
                                </Stack>
                            </div>
                            <div className={"Polaris-TextField__Backdrop"}></div>
                        </div>
                    </div>
                );
                const schedulingEndTimeIconContent = () => {
                    return (
                        <svg className="Polaris-Icon__Svg" viewBox="0 0 612 612" focusable="false" aria-hidden="true"><g><path d="M587.572,186.881c-32.266-75.225-87.096-129.934-162.949-162.285C386.711,8.427,346.992,0.168,305.497,0.168c-41.488,0-80.914,8.181-118.784,24.428C111.488,56.861,56.415,111.535,24.092,186.881C7.895,224.629,0,264.176,0,305.664c0,41.496,7.895,81.371,24.092,119.127c32.323,75.346,87.403,130.348,162.621,162.621c37.877,16.247,77.295,24.42,118.784,24.42c41.489,0,81.214-8.259,119.12-24.42c75.853-32.352,130.683-87.403,162.956-162.621C603.819,386.914,612,347.16,612,305.664C612,264.176,603.826,224.757,587.572,186.881z M538.724,440.853c-24.021,41.195-56.929,73.876-98.375,98.039c-41.195,24.021-86.332,36.135-134.845,36.135c-36.47,0-71.27-7.024-104.4-21.415c-33.129-14.384-61.733-33.294-85.661-57.215c-23.928-23.928-42.973-52.811-57.214-85.997c-14.199-33.065-21.08-68.258-21.08-104.735c0-48.52,11.921-93.428,35.807-134.509c23.971-41.231,56.886-73.947,98.039-98.04c41.146-24.092,85.99-36.142,134.502-36.142c48.52,0,93.649,12.121,134.845,36.142c41.446,24.164,74.283,56.879,98.375,98.039c24.092,41.153,36.135,85.99,36.135,134.509C574.852,354.185,562.888,399.399,538.724,440.853z"></path><path d="M324.906,302.988V129.659c0-10.372-9.037-18.738-19.41-18.738c-9.701,0-18.403,8.366-18.403,18.738v176.005c0,0.336,0.671,1.678,0.671,2.678c-0.671,6.024,1.007,11.043,5.019,15.062l100.053,100.046c6.695,6.695,19.073,6.695,25.763,0c7.694-7.695,7.188-18.86,0-26.099L324.906,302.988z"></path></g></svg>
                    );
                };
                let schedulingEndTimeHtml = (
                    <div className={"col-lg-6 col-md-12 col-sm-12 col-xs-12"}>
                        <Stack>
                            <Stack.Item fill>
                                <TextStyle variation="strong">End time</TextStyle>
                            </Stack.Item>
                        </Stack>
                        <div className={schedulingFieldClass}>
                            <div className={"Polaris-TextField__Input SchedulingCalendarWrap"}>
                                <Stack>
                                    <Stack.Item>
                                        <Icon source={schedulingEndTimeIconContent} />
                                    </Stack.Item>
                                    <Stack.Item>
                                        <TimePicker
                                            clearIcon={null}
                                            clockIcon={null}
                                            value={schedulingEndTime}
                                            onChange={this.handleChangeSchedulingEndTime}
                                            format={"h:mm a"}
                                            disableClock={true}
                                        />
                                    </Stack.Item>
                                </Stack>
                            </div>
                            <div className={"Polaris-TextField__Backdrop"}></div>
                        </div>
                        {schedulingEndTimeError}
                    </div>
                );

                if (schedulingType === 'period') {
                    let schedulingEndDateHtml = '';
                    if (schedulingEndDateCheckbox) {
                        let schedulingEndDateError = '';
                        let schedulingEndDateFieldClass = 'Polaris-TextField';
                        if (isSchedulingDateError) {
                            schedulingEndDateError = (
                                <div className="Polaris-Labelled__Error">
                                    <div className="Polaris-InlineError">
                                        <div className="Polaris-InlineError__Icon"><span className="Polaris-Icon"><svg
                                            className="Polaris-Icon__Svg" viewBox="0 0 20 20" focusable="false"
                                            aria-hidden="true"><path
                                            d="M10 18a8 8 0 1 1 0-16 8 8 0 0 1 0 16zm-1-8h2V6H9v4zm0 4h2v-2H9v2z"></path></svg></span>
                                        </div>
                                        End date is less then start
                                    </div>
                                </div>
                            );
                            schedulingEndDateFieldClass += " Polaris-TextField--error";
                        }


                        schedulingEndDateHtml = (
                            <div className={"row"}>
                                <div className={"col-lg-6 col-md-12 col-sm-12 col-xs-12"}>
                                    <Stack>
                                        <Stack.Item fill>
                                            <TextStyle variation="strong">End date</TextStyle>
                                        </Stack.Item>
                                    </Stack>
                                    <div className={schedulingEndDateFieldClass} onClick={this.handleEndCalendarActive}>
                                        <div className={"Polaris-TextField__Input SchedulingCalendarWrap"}>
                                            <Stack>
                                                <Stack.Item>
                                                    <Icon source={CalendarMajor} color="base"/>
                                                </Stack.Item>
                                                <Stack.Item>
                                                    <DatePicker
                                                        onChange={this.handleChangeSchedulingEndDate}
                                                        onCalendarOpen={this.handleEndCalendarActive}
                                                        onCalendarClose={this.handleEndCalendarInActive}
                                                        value={schedulingEndDate}
                                                        clearIcon={null}
                                                        calendarIcon={null}
                                                        format={"MM/dd/y"}
                                                        isOpen={endCalendarActive}
                                                    />
                                                </Stack.Item>
                                            </Stack>
                                        </div>
                                        <div className={"Polaris-TextField__Backdrop"}></div>
                                    </div>
                                    {schedulingEndDateError}
                                </div>
                                {schedulingEndTimeHtml}
                            </div>
                        )
                    }
                    schedulingOptions = (
                        <div>
                            <div className={"row"}>
                                <div className={"col-lg-6 col-md-12 col-sm-12 col-xs-12"}>
                                    <Stack>
                                        <Stack.Item fill>
                                            <TextStyle variation="strong">Start date</TextStyle>
                                        </Stack.Item>
                                    </Stack>
                                    <div className={"Polaris-TextField"} onClick={this.handleStartCalendarActive}>
                                        <div className={"Polaris-TextField__Input SchedulingCalendarWrap"}>
                                            <Stack>
                                                <Stack.Item>
                                                    <Icon source={CalendarMajor}  color="base"/>
                                                </Stack.Item>
                                                <Stack.Item>
                                                    <DatePicker
                                                        onChange={this.handleChangeSchedulingStartDate}
                                                        onCalendarOpen={this.handleStartCalendarActive}
                                                        onCalendarClose={this.handleStartCalendarInActive}
                                                        value={schedulingStartDate}
                                                        clearIcon={null}
                                                        calendarIcon={null}
                                                        format={"MM/dd/y"}
                                                        isOpen={startCalendarActive}
                                                    />
                                                </Stack.Item>
                                            </Stack>
                                        </div>
                                        <div className={"Polaris-TextField__Backdrop"}></div>
                                    </div>
                                </div>
                                {schedulingStartTimeHtml}
                            </div>
                            <div className={"schedulingEndDateCheckboxWrap"}>
                                <Checkbox
                                    checked={schedulingEndDateCheckbox}
                                    label={'Set End date'}
                                    onChange={this.handleChange('schedulingEndDateCheckbox')}
                                />
                            </div>
                            {schedulingEndDateHtml}
                        </div>
                    )

                } else if (schedulingType === "daily") {
                    let schedulingDailyEndTimeHtml = (
                        <div className={"row"}>
                            {schedulingEndTimeHtml}
                        </div>
                    )
                    if (!schedulingEndDateCheckbox) {
                        schedulingDailyEndTimeHtml = "";
                    }
                    schedulingOptions = (
                        <div>
                            <div className={"row"}>
                                {schedulingStartTimeHtml}
                            </div>
                            <div className={"schedulingEndDateCheckboxWrap"}>
                                <Checkbox
                                    checked={schedulingEndDateCheckbox}
                                    label={'Set End time'}
                                    onChange={this.handleChange('schedulingEndDateCheckbox')}
                                />
                            </div>
                            {schedulingDailyEndTimeHtml}
                        </div>
                    )
                } else  {

                    let schedulingStartDayHtml = '';
                    let schedulingEndDayHtml = '';

                    if(schedulingType === "weekly") {
                        schedulingStartDayHtml = (
                            <div>
                                <Select
                                    label={""}
                                    labelHidden={true}
                                    options={[
                                        {label: 'Monday', value: '1'},
                                        {label: 'Tuesday', value: '2'},
                                        {label: 'Wednesday', value: '3'},
                                        {label: 'Thursday', value: '4'},
                                        {label: 'Friday', value: '5'},
                                        {label: 'Saturday', value: '6'},
                                        {label: 'Sunday', value: '7'},
                                    ]}
                                    value={schedulingStartDay}
                                    onChange={this.handleChangeSchedulingStartDay}
                                />
                            </div>
                        );
                        schedulingEndDayHtml = (
                            <div>
                                <Select
                                    label={""}
                                    labelHidden={true}
                                    options={[
                                        {label: 'Monday', value: '1'},
                                        {label: 'Tuesday', value: '2'},
                                        {label: 'Wednesday', value: '3'},
                                        {label: 'Thursday', value: '4'},
                                        {label: 'Friday', value: '5'},
                                        {label: 'Saturday', value: '6'},
                                        {label: 'Sunday', value: '7'},
                                    ]}
                                    value={schedulingEndDay}
                                    onChange={this.handleChangeSchedulingEndDay}
                                />
                            </div>
                        );
                    } else {
                        let choicesDaysList = [];
                        for (let i = 1; i <= 31; i++) {
                            let choiceDayItem =  {label: i.toString(), value: i.toString()};
                            choicesDaysList.push(choiceDayItem);
                        }
                        schedulingStartDayHtml = (
                            <div>
                                <Select
                                    label={""}
                                    labelHidden={true}
                                    options={choicesDaysList}
                                    value={schedulingStartDay}
                                    onChange={this.handleChangeSchedulingStartDay}
                                />
                            </div>
                        );
                        schedulingEndDayHtml = (
                            <div>
                                <Select
                                    label={""}
                                    labelHidden={true}
                                    options={choicesDaysList}
                                    value={schedulingEndDay}
                                    onChange={this.handleChangeSchedulingEndDay}
                                />
                            </div>
                        );
                    }

                    schedulingOptions = (
                        <div>
                            <div className={"row"}>
                                <div className={"col-lg-6 col-md-12 col-sm-12 col-xs-12"}>
                                    <Stack>
                                        <Stack.Item fill>
                                            <TextStyle variation="strong">Start day</TextStyle>
                                        </Stack.Item>
                                    </Stack>
                                    {schedulingStartDayHtml}
                                </div>
                                {schedulingStartTimeHtml}
                            </div>
                            <div className={"row schedulingEndDayWrap"}>
                                <div className={"col-lg-6 col-md-12 col-sm-12 col-xs-12"}>
                                    <Stack>
                                        <Stack.Item fill>
                                            <TextStyle variation="strong">End day</TextStyle>
                                        </Stack.Item>
                                    </Stack>
                                    {schedulingEndDayHtml}
                                </div>
                                {schedulingEndTimeHtml}
                            </div>
                        </div>
                    )
                }

                schedulingHtml = (
                    <div>
                        <div id={"priceSchedulingSectionShow"}>
                            <Link monochrome={1} onClick={this.handleToggleScheduling}>
                                Apply scheduling?
                            </Link>
                            <Checkbox
                                checked={schedulingCheckbox}
                                label={'Enable'}
                                onChange={this.handleToggleScheduling}
                            />
                        </div>

                        <div  id={"priceSchedulingSection"}>
                            <div>

                                <ChoiceList
                                    title={""}
                                    titleHidden={true}
                                    choices={[
                                        {label: 'One time', value: 'period'},
                                        {label: 'Repeat daily', value: 'daily'},
                                        {label: 'Repeat weekly', value: 'weekly'},
                                        {label: 'Repeat monthly', value: 'monthly'},
                                    ]}
                                    selected={schedulingType}
                                    onChange={this.handleSchedulingTypeChange}
                                />
                            </div>
                            <div id={"schedulingOptionsWrap"}>
                                {schedulingOptions}
                            </div>

                        </div>
                    </div>
                )
            }


            let updateNoticeHtml = (
                <div>
                    <h4>By continuing you are agreeing to the following:</h4>
                    <div>- You have a backup of your products or recently performed an export of your products.</div>
                    <div>- You have already tested our app by updating a couple of test products.</div>
                    <div>- You understand how the app works and accept responsibility for your price updates.</div>
                </div>
            );
            if(selectedItems === "All" && appliedFilters.length === 0 &&
                (searchValue === null || searchValue === "")) {
                updateNoticeHtml = (
                    <div>
                        <h4>We noticed you selected all your products! By continuing you are agreeing to the following:</h4>
                        <div>- You have a backup of your products or recently performed an export of your products.</div>
                        <div>- You have already tested our app by updating a couple of test products.</div>
                        <div>- You understand how the app works and accept responsibility for your price updates.</div>
                    </div>
                );
            }
            let updateNoticeModal = (
                <div>
                    <Modal
                        open={showUpdateNoticeModal}
                        onClose={this.handleToggleEditActive}
                        title="Confirm your update"
                        primaryAction={{
                            content: 'Start Update',
                            onAction: this.handleUpdateConfirmed,
                        }}
                        secondaryActions={[
                            {
                                content: 'Test more',
                                onAction: this.handleToggleUpdateNotice,
                            },
                        ]}
                    >
                        <Modal.Section>
                            {updateNoticeHtml}
                        </Modal.Section>
                    </Modal>
                </div>
            )


            return (

                <Card id={"Polaris-Card-Actions-List"} title={"2. Select how you want to update prices and click Submit"}>
                    <div id={"actionsCardWrap"}>
                    <Card.Section title="Actions">
                        <Form noValidate name="price" onSubmit={this.handlePriceSubmit}>
                            <FormLayout>
                                <div id={"formLayoutFields"}>
                                    <FormLayout.Group condensed >

                                        <Select
                                            label={""}
                                            labelHidden={true}
                                            style={{width: '315px'}}
                                            value={updatePriceSelect}
                                            options={[
                                                {
                                                    label: 'Discount',
                                                    value: 'discount'
                                                },
                                                {label: 'Update \'Price\'', value: 'update'},
                                                {
                                                    label: 'Update \'Price\' using \'Compare At Price\'',
                                                    value: 'update_based_on_compare_at_price',

                                                },
                                                {
                                                    label: 'Update \'Price\' with \'Compare At Price\' and empty \'Compare At Price\'',
                                                    value: 'update_with_compare_at_price',

                                                },
                                                {label: 'Update \'Compare at Price\'', value: 'update_compare_at_price'},
                                                {
                                                    label: 'Update \'Compare at Price\' using \'Price\'',
                                                    value: 'update_based_on_price',

                                                },
                                                {
                                                    label: 'Update \'Price\' using \'Cost\'',
                                                    value: 'update_price_with_cost'},
                                                {
                                                    label: 'Update \'Compare at Price\' using \'Cost\'',
                                                    value: 'update_compare_at_price_with_cost'
                                                },
                                            ]}
                                            onChange={this.handlePriceSelectChange}
                                        />
                                        <Select
                                            label={""}
                                            labelHidden={true}
                                            style={{width: '50px'}}
                                            value={updatePriceActionType}
                                            options={[
                                                {label: 'By', value: 'by'},
                                                {label: 'To', value: 'to'},
                                            ]}
                                            onChange={this.handleChange('updatePriceActionType')}
                                            disabled={shouldBeDisabledCheckbox}
                                            id={'updatePriceActionTypePlaceholder'}
                                        />
                                        <TextField
                                            label={""}
                                            labelHidden={true}
                                            autoComplete={"off"}
                                            readOnly={false}
                                            value={updatePriceAmount}
                                            onChange={this.handleChange('updatePriceAmount')}
                                            labelInline
                                            type="url"
                                            disabled={shouldBeDisabledPrice}
                                            placeholder={updatePricePlaceholder}
                                            id={'updatePriceAmountPlaceholder'}
                                        />
                                        <Checkbox
                                            checked={updatePriceCheckbox}
                                            label={updatePriceCheckboxlabel}
                                            onChange={this.handleChange('updatePriceCheckbox')}
                                            disabled={shouldBeDisabledCheckbox}
                                        />
                                    </FormLayout.Group>
                                    <div id={"priceRoundingSectionShow"}>
                                        <Link monochrome={1} onClick={this.handleToggleRounding}>
                                            Apply rounding?
                                        </Link>
                                    </div>

                                    <div className={"row"} id={"priceRoundingSection"}>
                                        <div className={"col-lg-6 col-md-12 col-sm-12 col-xs-12"}>
                                            <div>
                                                <Checkbox
                                                    checked={priceRoundingCheckbox}
                                                    label={'Enable'}
                                                    onChange={this.handleChange('priceRoundingCheckbox')}
                                                    disabled={shouldBeDisabledPriceRounding}
                                                />

                                            </div>
                                            <div id={"updatePriceRoundStepWrap"}>
                                                <TextField
                                                    label={""}
                                                    labelHidden={true}
                                                    autoComplete={"off"}
                                                    type="number"
                                                    value={updatePriceRoundStep}
                                                    onChange={this.handleChange('updatePriceRoundStep')}
                                                    step={0.01}
                                                    min={0.01}
                                                    max={0.99}
                                                    disabled={shouldBeDisabledPriceRounding}
                                                    prefix={shopInfo.currencySymbol}
                                                />
                                            </div>
                                            <div>
                                                <Checkbox
                                                    checked={roundToNearestValueCheckbox}
                                                    label={'Round the nearest Whole number'}
                                                    onChange={this.handleChange('roundToNearestValueCheckbox')}

                                                />
                                            </div>
                                        </div>
                                        <div className={"col-lg-6 col-md-12 col-sm-12 col-xs-12"}>
                                            <TextStyle variation="subdued">
                                                <Stack>
                                                    <Stack.Item fill>
                                                        <TextStyle variation="strong">Example</TextStyle>
                                                    </Stack.Item>
                                                </Stack>
                                                <Stack>
                                                    <Stack.Item fill>Discount</Stack.Item>
                                                    <Stack.Item>{discount}%</Stack.Item>
                                                </Stack>
                                                <Stack>
                                                    <Stack.Item fill>Original price</Stack.Item>
                                                    <Stack.Item>{shopInfo.currencySymbol}{originalPrice}</Stack.Item>
                                                </Stack>
                                                <Stack>
                                                    <Stack.Item fill>Discounted price</Stack.Item>
                                                    <Stack.Item>{shopInfo.currencySymbol}{discountedPrice}</Stack.Item>
                                                </Stack>
                                                <Stack>
                                                    <Stack.Item fill>Rounded price</Stack.Item>
                                                    <Stack.Item>{shopInfo.currencySymbol}{roundedPrice}</Stack.Item>
                                                </Stack>
                                            </TextStyle>
                                        </div>
                                    </div>
                                    {schedulingHtml}
                                </div>

                                <div id={"buttonFormLayoutWrap"}>
                                    <FormLayout.Group>
                                        {submitPriceButton}
                                    </FormLayout.Group>
                                </div>
                            </FormLayout>
                        </Form>
                    </Card.Section>
                    </div>
                    {updateNoticeModal}
                </Card>

        );
        };


        let notProHover = '';
        let notProUpdate = '';
        if(!SHOPIFY_IS_PRO){
            notProHover =  (
                    <div id={"upgradeToProHover"}>
                    </div>
            )

            notProUpdate =  (
                <div id={"upgradeToPro"}>
                    <div>
                        <h1>Upgrade to Pro for History, Rollback and Scheduling</h1>
                        <Link id={'upgradeNowBtnPro'} url="/billing?type=pro">Upgrade</Link>
                    </div>
                </div>
            )
        }

        let mainHeaderPlanInfo = '';
        if(SHOPIFY_IS_TRIAL){
            mainHeaderPlanInfo = (
                <h3 id={"mainHeaderPlanInfo"}>
                    You are using trial version and are limited to {SHOPIFY_TRIAL_MODE_LIMIT} updates.
                    <Link id={'upgradeNowBtnTitle'} url="/billing">Upgrade</Link>
                </h3>
            )
        } else if(SHOPIFY_IS_USAGE_CHARGE && !SHOPIFY_IS_USAGE_CHARGE_MADE){
            mainHeaderPlanInfo = (
                <div id={"Polaris-Card-Syncing-Trial-Used"}>
                    <SyncTrialItemsLoader/>
                </div>
            )
        } else if(!SHOPIFY_IS_PRO){
            mainHeaderPlanInfo = (
                <h3 id={"mainHeaderPlanInfo"}>
                    You are on Basic plan, upgrade to Pro for price History, Rollback and Scheduling.
                    <Link id={'upgradeNowBtnTitle'} url="/billing?type=pro">Upgrade</Link>
                </h3>
            )
        } else {
            mainHeaderPlanInfo = (
                <h3 id={"mainHeaderPlanInfo"}>
                    You are on Pro Plan.
                </h3>
            )
        }

        let noticeHeader = (
            <div id={"noticeInfoBanner"}>
                <Banner
                    status="warning"
                >
                    <p>
                        You can't use collection filter together with title search or tag filter
                    </p>
                </Banner>
            </div>
        );

        return (
                    <Card >
                        {mainHeaderPlanInfo}
                        {noticeHeader}
                        <div className={"row"}>
                            <div id={"Polaris-Card-Products-List"} className={"Polaris-Card  col-lg-6 col-md-12 col-sm-12 col-xs-12"}>
                                <Card title={"1. Search, filter and select the products you wish to update"}>
                                    {paginationMarkup}
                                    <Card>
                                        <ResourceList
                                            showHeader={true}
                                            resourceName={resourceName}
                                            items={items}
                                            renderItem={(product, id, index) => { return <ProductListItem shopInfo={shopInfo} shopDomain={shopDomain} {...product} />; }}
                                            selectedItems={selectedItems}
                                            onSelectionChange={this.handleSelectionChange}
                                            promotedBulkActions={[
                                                // { content: 'Edit products', onAction: this.handleBulkEdit },
                                                { content: 'Update Prices', onAction: this.handleBulkUpdatePrices },
                                            ]}
                                            loading={this.state.loading}
                                            bulkActions={[

                                            ]}
                                            filterControl={
                                                <Sticky>
                                                    <div id={"resourceListFiltersWrap"}>
                                                        <Filters
                                                            filters={availableFilters}
                                                            appliedFilters={appliedFilters}
                                                            queryValue={searchValue || ''}
                                                            onQueryChange={this.handleSearchChange}
                                                            onQueryClear={this.handleSearchClear}
                                                            onClearAll={this.handleClearAll}
                                                        />
                                                    </div>
                                                </Sticky>

                                            }
                                            hasMoreItems
                                        />
                                    </Card>
                                    {paginationMarkupDown}
                                </Card>
                            </div>
                            <div className={"col-lg-6 col-md-12 col-sm-12 col-xs-12"}  id={"Polaris-Card-Right-Column"}>
                                {actionsMarkup()}
                                {payingNotice}
                                <div id={"Polaris-Card-Syncing-Status"}>
                                    <SyncLoader/>
                                </div>
                                <MassUpdateLoader refresh={this.state.refreshLoader} onChangeState={this.handleUpdatesAndScheduling} onFinished={this.handleUpdate}/>
                                <div id={"Polaris-Card-Updates-List"}>
                                    <Card.Section title="History and Rollback" >
                                        <div id={"mainPageHistoryBlockWrap"}>
                                            <UpdatesList onChangeState={this.handleUpdateScheduling} isMainPage={1} refresh={this.state.refreshUpdates}/>
                                            <div id={"viewAllHistoryWrap"}>
                                                <Link onClick={this.handleViewAllHistory}>View all</Link>
                                            </div>
                                            {notProHover}
                                            {notProUpdate}
                                        </div>
                                    </Card.Section>
                                </div>
                                <div id={"Polaris-Card-ScheduledUpdates-List"}>
                                    <Card.Section title="Scheduling" >
                                        <div id={"mainPageSchedulingBlockWrap"}>
                                            <ScheduledUpdatesList onChangeState={this.handleUpdates} isMainPage={1} refresh={this.state.refreshScheduling}/>
                                            <div id={"viewAllSchedulingWrap"}>
                                                <Link onClick={this.handleViewAllScheduling}>View all</Link>
                                            </div>
                                            {notProHover}
                                            {notProUpdate}
                                        </div>
                                    </Card.Section>
                                </div>
                            </div>

                        </div>
                    </Card>
        );
    }

    handlePreviousPage() {
        let prevPage = this.state.currentPage - 1;
        let searchValue = this.state.searchValue;
        let appliedFilters = this.state.appliedFilters;
        this.fetchProducts({page: prevPage, title: searchValue, appliedFilters: appliedFilters, paginationType: 'previous'});
    }


    handleViewAllHistory(){
        $('#updates-tab').click();
        return false;
    }

    handleViewAllScheduling(){
        $('#scheduled_updates-tab').click();
        return false;
    }

    handleNextPage() {
        let nextPage = this.state.currentPage + 1;
        let searchValue = this.state.searchValue;
        let appliedFilters = this.state.appliedFilters;
        this.fetchProducts({page: nextPage, title: searchValue, appliedFilters: appliedFilters, paginationType: 'next'});

    }

    handleFilterRemove(key) {
        if(this.isEmpty(key)) {
            return;
        }
        let searchValue = this.state.searchValue;
        let appliedFilters = this.state.appliedFilters;
        let newAppliedFilters = appliedFilters.filter(function( filter ) {
            return filter.key !== key;
        });
        if(appliedFilters.length !== newAppliedFilters.length) {
            this.setState({ appliedFilters: newAppliedFilters, selectedItems: [] });
            this.fetchProducts({title: searchValue, appliedFilters: newAppliedFilters, resetPagination: true});
        }
    }

    handleProductTypeFilterChange(value) {
        let searchValue = this.state.searchValue;
        let appliedFilters = this.state.appliedFilters;

        appliedFilters = appliedFilters.filter(function( filter ) {
            return filter.key !== "productTypeFilter";
        });
        if(!this.isEmpty(value)){
            appliedFilters.push({
                key: "productTypeFilter",
                label: "Product Type is "+value,
                value: value,
                onRemove: this.handleFilterRemove
            });
        }

        this.setState({ appliedFilters: appliedFilters, selectedItems: []  });
        this.fetchProducts({title: searchValue, appliedFilters: appliedFilters, resetPagination: true});
    }

    handleCollectionFilterChange(value) {
        let searchValue = this.state.searchValue;
        let appliedFilters = this.state.appliedFilters;

        appliedFilters = appliedFilters.filter(function( filter ) {
            return filter.key !== 'collectionFilter';
        });
        console.log(value)
        if(!this.isEmpty(value)){
            appliedFilters.push({
                key: "collectionFilter",
                label: "Collection is "+this.state.availableCollections[value],
                value: value,
                onRemove: this.handleFilterRemove
            });
            appliedFilters = appliedFilters.filter(function( filter ) {
                return filter.key !== 'tagFilter';
            });
            searchValue = '';
            this.setState({ searchValue: searchValue  });
        }
        this.setState({ appliedFilters: appliedFilters, selectedItems: []  });
        this.fetchProducts({title: searchValue, appliedFilters: appliedFilters, resetPagination: true});
    }

    handleVendorFilterChange(value) {
        let searchValue = this.state.searchValue;
        let appliedFilters = this.state.appliedFilters;

        appliedFilters = appliedFilters.filter(function( filter ) {
            return filter.key !== 'vendorFilter';
        });
        if(!this.isEmpty(value)){
            appliedFilters.push({
                key: "vendorFilter",
                label: "Vendor is "+value,
                value: value,
                onRemove: this.handleFilterRemove
            });
        }
        this.setState({ appliedFilters: appliedFilters, selectedItems: []  });
        this.fetchProducts({title: searchValue, appliedFilters: appliedFilters, resetPagination: true});

    }

    handleTagFilterChange(value) {
        let searchValue = this.state.searchValue;
        let appliedFilters = this.state.appliedFilters;

        appliedFilters = appliedFilters.filter(function( filter ) {
            return filter.key !== 'tagFilter';
        });
        if(!this.isEmpty(value)){
            appliedFilters.push({
                key: "tagFilter",
                label: "Tag is "+value,
                value: value,
                onRemove: this.handleFilterRemove
            });
            appliedFilters = appliedFilters.filter(function( filter ) {
                return filter.key !== 'collectionFilter';
            });
        }
        this.setState({ appliedFilters: appliedFilters, searchValue: searchValue, selectedItems: []  });
        this.fetchProducts({title: searchValue, appliedFilters: appliedFilters, resetPagination: true});
    }

    handleClearAll(value) {
        this.setState({ appliedFilters: [], searchValue: '', selectedItems: []  });
        this.fetchProducts({title: '', appliedFilters: [], resetPagination: true});
    }

    handleSearchChange(searchValue) {
        clearTimeout(typingTimer);
        let object = this;
        typingTimer = setTimeout(function(){
            let appliedFilters = object.state.appliedFilters;
            appliedFilters = appliedFilters.filter(function( filter ) {
                return filter.key !== 'collectionFilter';
            });
            object.setState({ appliedFilters: appliedFilters });
            object.fetchProducts({title: searchValue, appliedFilters: appliedFilters, resetPagination: true});
        }, 1000);
        this.setState({ searchValue: searchValue  });
    }

    handleSearchClear() {
        this.setState({ searchValue: ''  });
        this.fetchProducts({title: '', appliedFilters: [], resetPagination: true});
    }


    handleSelectionChange(selectedItems) {
        this.setState({ selectedItems });
    }

    handleUpdate(){
        this.fetchProducts({page: this.state.currentPage, title: this.state.searchValue, appliedFilters: this.state.appliedFilters});
        this.setState({refreshUpdates: !this.state.refreshUpdates});
        this.setState({refreshScheduling: !this.state.refreshScheduling});
    }

    handleUpdateScheduling() {
        this.setState({refreshScheduling: !this.state.refreshScheduling});
    }

    handleUpdates() {
        this.setState({refreshUpdates: !this.state.refreshUpdates});
    }

    handleUpdatesAndScheduling() {
        this.setState({refreshScheduling: !this.state.refreshScheduling});
        this.setState({refreshUpdates: !this.state.refreshUpdates});
    }

    handleToggleRounding(){

        if($('#priceRoundingSection').css('display') == 'none'){
            $('#priceRoundingSection').css('display', 'flex');
        } else {
            $('#priceRoundingSection').css('display', 'none');
        }
    }

    handleToggleScheduling(){

        if($('#priceSchedulingSection').css('display') == 'none'){
            $('#priceSchedulingSection').css('display', 'block');
            this.setState({schedulingCheckbox: 1});
        } else {
            $('#priceSchedulingSection').css('display', 'none');
            this.setState({schedulingCheckbox: 0});
        }
    }

    handleSchedulingTypeChange(schedulingType) {
        this.setState({ schedulingType: schedulingType[0], schedulingStartDay: "1", schedulingEndDay: "2"  });
        this.handleSchedulingValidationCheck(schedulingType);
    }

    handleStartCalendarActive() {
        this.setState({ startCalendarActive: true  });
    }

    handleStartCalendarInActive() {
        this.setState({ startCalendarActive: false  });
    }

    handleEndCalendarActive() {
        this.setState({ endCalendarActive: true  });
    }

    handleEndCalendarInActive() {
        this.setState({ endCalendarActive: false  });
    }

    handleChangeSchedulingStartDate(schedulingStartDate) {
        this.setState({ schedulingStartDate: schedulingStartDate, startCalendarActive: false  });
        this.handleSchedulingValidationCheck(false, schedulingStartDate);
    }

    handleChangeSchedulingEndDate(schedulingEndDate) {
        this.setState({ schedulingEndDate: schedulingEndDate, endCalendarActive: false  });
        this.handleSchedulingValidationCheck(false, false, schedulingEndDate);
    }

    handleChangeSchedulingStartTime(schedulingStartTime) {
        this.setState({ schedulingStartTime: schedulingStartTime});
        this.handleSchedulingValidationCheck(false, false, false,
            schedulingStartTime);
    }

    handleChangeSchedulingEndTime(schedulingEndTime) {
        this.setState({ schedulingEndTime: schedulingEndTime});
        this.handleSchedulingValidationCheck(false, false, false,
            false, schedulingEndTime);
    }

    handleChangeSchedulingStartDay(schedulingStartDay) {
        this.setState({ schedulingStartDay: schedulingStartDay });
    }

    handleChangeSchedulingEndDay(schedulingEndDay) {
        this.setState({ schedulingEndDay: schedulingEndDay });
    }

    handleSchedulingValidationCheck(schedulingType = false, schedulingStartDate = false, schedulingEndDate = false,
                                    schedulingStartTime = false, schedulingEndTime = false) {
        schedulingType = !schedulingType ? this.state.schedulingType : schedulingType;
        schedulingStartDate = !schedulingStartDate ? this.state.schedulingStartDate : schedulingStartDate;
        schedulingEndDate = !schedulingEndDate ? this.state.schedulingEndDate : schedulingEndDate;
        schedulingStartTime = !schedulingStartTime ? this.state.schedulingStartTime : schedulingStartTime;
        schedulingEndTime = !schedulingEndTime ? this.state.schedulingEndTime : schedulingEndTime;
        if(schedulingType !== 'period'){
            this.setState({ isSchedulingTimeError: false, isSchedulingDateError: false });
            return;
        }
        let startDateWithoutHours = _.clone(schedulingStartDate);
        startDateWithoutHours.setHours(0,0,0,0);
        let endDateWithoutHours = _.clone(schedulingEndDate);
        endDateWithoutHours.setHours(0,0,0,0);

        let startTimeParts = schedulingStartTime.split(":");
        let endTimeParts = schedulingEndTime.split(":");
        let startTimeCheck = new Date(0, 0, 0, startTimeParts[0], startTimeParts[1], 0, 0);
        let endTimeCheck = new Date(0, 0, 0, endTimeParts[0], endTimeParts[1], 0, 0);

        if(Date.parse(endDateWithoutHours) === Date.parse(startDateWithoutHours) && endTimeCheck <= startTimeCheck) {
            this.setState({ isSchedulingTimeError: true });
        } else {
            this.setState({ isSchedulingTimeError: false });
        }

        if(endDateWithoutHours < startDateWithoutHours) {
            this.setState({ isSchedulingDateError: true });
        } else {
            this.setState({ isSchedulingDateError: false });
        }
    }

    handleBulkEdit() {
        let selectedItems = this.state.selectedItems;



        let itemsStr = '';
        if(selectedItems !== 'All'){

            let selectedProducts = [];

            for (let index = 0; index < selectedItems.length; ++index) {
                let variantId = selectedItems[index];
                let product = this.state.items.filter(product => product.id === variantId);
                if(typeof product[0] !== 'undefined'){
                    selectedProducts.push(product[0].product_id);
                }
            }

            itemsStr = selectedProducts.join(",");
        }
        var url = "https://"+this.state.shopDomain+"/admin/bulk?resource_name=Product&edit=variants.sku,variants.price,variants.compare_at_price&show=&return_to=/admin/products&metafield_titles=&metafield_options=&ids="+itemsStr;
        window.open(url);
    }

    handleBulkUpdatePrices() {
        var url = location.href;               //Save down the URL without hash.
        location.href = "#Polaris-Card-Right-Column";                 //Go to the target element.
        history.replaceState(null,null,location.href);
    }


    handleSaveFilters() {

    }

    handleToggleUpdateNotice() {
        this.setState({
            showUpdateNoticeModal: !this.state.showUpdateNoticeModal,
        });
    }

    handleUpdateConfirmed() {
        this.setState({
            showUpdateNoticeModal: false,
        });
        this.handlePriceSubmit("confirm");
    }

    isEmpty(value) {
        if (Array.isArray(value)) {
            return value.length === 0;
        } else {
            return value === '' || value == null;
        }
    }

    componentWillUnmount() {
        this._isMounted = false;
    }
}
