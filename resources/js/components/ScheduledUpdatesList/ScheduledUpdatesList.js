import React, {Component} from 'react';
import TimePicker from 'react-time-picker';
import 'react-calendar/dist/Calendar.css';
import DatePicker from 'react-date-picker';


import {
    Card,
    ResourceList,
    Pagination,
    Stack,
    Button, TextStyle, Icon, Checkbox, Select, ChoiceList, Modal
} from '@shopify/polaris';
import '@shopify/polaris/build/esm/styles.css';


import ScheduledUpdateListItem from './ScheduledUpdateListItem';
import IndexPagination from '../IndexPagination/IndexPagination';
import {CalendarMajor} from "@shopify/polaris-icons";

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
            "scheduling_description": "",
            "status": "",
            "sub_status": "",
            "sub_status_text": "",
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


export default class ScheduledUpdatesList extends Component {
    _isMounted = false;
    constructor(props) {
        super(props);
        let {
            isMainPage
        } = props;

        if(isNaN(isMainPage)){
            isMainPage = 0;
        }
        let curDate = new Date();
        let tomorrowDate = new Date(new Date().getTime() + 24 * 60 * 60 * 1000);
        let curTime = curDate.getHours()+":"+(curDate.getMinutes()<10?'0':'') + curDate.getMinutes();
        let tomorrowTime = tomorrowDate.getHours()+":"+(tomorrowDate.getMinutes()<10?'0':'') + tomorrowDate.getMinutes();
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
            showEditModal: false,
            schedulingId: 0,
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
            isUpdateButtonDisabled: false,
            refreshChildren: false,
        };



        this.fetchUpdates = this.fetchUpdates.bind(this);
        this.handlePreviousPage = this.handlePreviousPage.bind(this);
        this.handlePreviousPage = this.handlePreviousPage.bind(this);
        this.handleNextPage = this.handleNextPage.bind(this);
        this.handleToggleEditActive = this.handleToggleEditActive.bind(this);

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
        this.handleEditItem = this.handleEditItem.bind(this);
        this.handleUpdateSubmit = this.handleUpdateSubmit.bind(this);
        this.handleChangeItemState = this.handleChangeItemState.bind(this);
        this.handleRefreshChildren = this.handleRefreshChildren.bind(this);
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
            url: '/getScheduledUpdates',
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
            trialItemsUsed,
            showEditModal,
            schedulingType, schedulingStartDate, schedulingEndDate,
            schedulingStartTime, schedulingEndTime, startCalendarActive, endCalendarActive,
            schedulingEndDateCheckbox, schedulingStartDay, schedulingEndDay, isSchedulingTimeError,
            isSchedulingDateError
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
                                <Icon source={schedulingStartTimeIconContent}/>
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
                                <Icon source={schedulingEndTimeIconContent}/>
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
                if(isSchedulingDateError) {
                    schedulingEndDateError = (
                        <div className="Polaris-Labelled__Error">
                            <div className="Polaris-InlineError">
                                <div className="Polaris-InlineError__Icon"><span className="Polaris-Icon"><svg
                                    className="Polaris-Icon__Svg" viewBox="0 0 20 20" focusable="false" aria-hidden="true"><path
                                    d="M10 18a8 8 0 1 1 0-16 8 8 0 0 1 0 16zm-1-8h2V6H9v4zm0 4h2v-2H9v2z"></path></svg></span></div>
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
                                            <Icon source={CalendarMajor} color="base"/>
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

        let schedulingHtml = (
            <div>
                <div  id={"priceSchedulingSectionEdit"}>
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
        );


        return (
            <div>
                <div id={"scheduled_updates_content"}>
                    <Card>
                        <div id={"Polaris-Card-ScheduledUpdates-List"} className={"Polaris-Card"}>
                            {paginationMarkup}
                            <Card>
                                <ResourceList
                                    resourceName={resourceName}
                                    items={items}
                                    renderItem={(update) => { return <ScheduledUpdateListItem onChangeItemState={this.handleChangeItemState} onRefreshChildren={this.handleRefreshChildren} handleEditItem = {this.handleEditItem}  shopInfo={shopInfo} shopDomain={shopDomain} {...update} />} }
                                    loading={this.state.loading}
                                    hasMoreItems
                                />


                                {paginationMarkup}
                            </Card>
                        </div>
                    </Card>
                </div>
                <div>
                    <Modal
                        open={showEditModal}
                        onClose={this.handleToggleEditActive}
                        title="Edit scheduling item"
                        primaryAction={{
                            content: 'Save',
                            onAction: this.handleUpdateSubmit,
                            loading: this.state.isUpdateButtonDisabled,
                            disabled: this.state.isUpdateButtonDisabled,
                        }}
                        secondaryActions={[
                            {
                                content: 'Cancel',
                                onAction: this.handleToggleEditActive,
                            },
                        ]}
                    >
                        <Modal.Section>
                            {schedulingHtml}
                        </Modal.Section>
                    </Modal>
                </div>

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

    handleToggleEditActive() {
        this.setState({ showEditModal: !this.state.showEditModal });
    }


    handleUpdate(){
        this.fetchUpdates({page: this.state.currentPage});
    }

    handleToggleScheduling(){

        if($('#priceSchedulingSection').css('display') === 'none'){
            $('#priceSchedulingSection').css('display', 'block');
        } else {
            $('#priceSchedulingSection').css('display', 'none');
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

    handleEditItem(item){
        let schedulingEndDateCheckbox = !!item.end_date;
        if(item.type === "daily") {
            schedulingEndDateCheckbox = !!item.end_time;
        }
        let curDate = new Date();
        let tomorrowDate = new Date(new Date().getTime() + 24 * 60 * 60 * 1000);
        let curTime = curDate.getHours()+":"+(curDate.getMinutes()<10?'0':'') + curDate.getMinutes();
        let tomorrowTime = tomorrowDate.getHours()+":"+(tomorrowDate.getMinutes()<10?'0':'') + tomorrowDate.getMinutes();

        let start_date = item.start_date ? new Date(item.start_date) : curDate;
        let start_time = item.start_time ? item.start_time : curTime;
        let end_date = item.end_date ? new Date(item.end_date) : tomorrowDate;
        let end_time = item.end_time ? item.end_time : tomorrowTime;
        let start_day = item.start_day ? item.start_day : "1";
        let end_day = item.end_day ? item.end_day : "2";

        this.setState({
            schedulingType: item.type,
            schedulingStartDate: start_date,
            schedulingEndDate:  end_date,
            schedulingStartTime: start_time,
            schedulingEndTime: end_time,
            schedulingStartDay: start_day,
            schedulingEndDay: end_day,
            schedulingId: item.id,
            isSchedulingDateError: false,
            isSchedulingTimeError: false,
            schedulingEndDateCheckbox: schedulingEndDateCheckbox,
            showEditModal: true,
        });
    }

    handleChange = (field) => {
        return (value) => this.setState({[field]: value});
    };


    handleUpdateSubmit() {
        if (this.state.schedulingEndDateCheckbox &&
                (this.state.isSchedulingDateError || this.state.isSchedulingTimeError)) {
            return;
        }
        // if(!confirm('Are you sure?')){
        //     return;
        // }
        let data = {
            scheduling_id: this.state.schedulingId,
            scheduling_type: this.state.schedulingType
        };
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

        return this.updateSchedulingItem(data);
    };


    updateSchedulingItem(options) {

        this.setState({ isUpdateButtonDisabled: true});
        currentRequest = $.ajax({
            url: '/updateScheduling',
            method: 'POST',
            data: options,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
            }
        });
        currentRequest.then(data => {
            currentRequest = null;
            this.fetchUpdates({page: this.state.currentPage});
            this.setState({
                showEditModal: false,
                isUpdateButtonDisabled: false,
            });
        });
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
