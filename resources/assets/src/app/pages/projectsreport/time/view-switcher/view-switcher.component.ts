import { Component, Output, EventEmitter, ViewChild, OnInit } from '@angular/core';
import { DateRangeSelectorComponent } from '../date-range-selector/date-range-selector.component';
import * as moment from 'moment';
import { ApiService } from '../../../../api/api.service';
import { User } from '../../../../models/user.model';
import {DateSelectorComponent} from '../date-selector/date-selector.component';
import { LocalStorage } from '../../../../api/storage.model';

export interface ViewData {
    name: string;
    start: moment.Moment;
    end: moment.Moment;
    timezone: string;
}

@Component({
    selector: 'view-switcher',
    templateUrl: './view-switcher.component.html',
    styleUrls: ['./view-switcher.component.scss']
})
export class ViewSwitcherComponent implements OnInit {
    @ViewChild('dateRangeSelector') dateRangeSelector: DateRangeSelectorComponent;
    @ViewChild('dateSelector') dateSelector: DateSelectorComponent;

    start: moment.Moment = moment.utc();
    end: moment.Moment = moment.utc().add(1, 'day');

    timezone = 'Asia/Omsk';

    userInteraction = false;
    viewName = 'timelineDay';
    activeButton = 'timelineDay';
    filterByDate = null;
    filterByDateRange = null;

    onChange = null;
    @Output() setView = new EventEmitter<ViewData>();

    get view(): ViewData {
        return {
            name: this.viewName,
            start: this.start,
            end: this.end,
            timezone: this.timezone,
        };
    }

    constructor(private api: ApiService) {

    }
    ngOnInit() {
        this.filterByDate = LocalStorage.getStorage().get(`filterByDateIN${ window.location.pathname }`);
        if (this.filterByDate === null) {
          LocalStorage.getStorage().set(`filterByDateIN${ window.location.pathname }`, this.viewName); 
        }
        this.filterByDate = LocalStorage.getStorage().get(`filterByDateIN${ window.location.pathname }`);
        this.viewName = this.filterByDate;
        this.activeButton = this.filterByDate;

        const user = this.api.getUser() as User;

        let timezone = localStorage.getItem('statistics-timezone');
        if (timezone === null) {
            timezone = user.timezone !== null ? user.timezone : 'Asia/Omsk';
        }

        this.timezone = timezone;
    }

    applyChanges() {
        this.setView.emit(this.view);
    }

    changeViewName(viewName: string) {
        this.activeButton = viewName;
        this.viewName = viewName;       
        this.applyChanges();
        LocalStorage.getStorage().set(`filterByDateIN${ window.location.pathname }`, this.viewName);
    }

    changeStartDate(date: moment.Moment) {
        this.start = date;

        switch (this.viewName) {
            case 'timelineDay': this.end = date.clone().add(1, 'day'); break;
            case 'timelineWeek': this.end = date.clone().add(1, 'week'); break;
            case 'timelineMonth': this.end = date.clone().add(1, 'month'); break;
            default: break;
        }

        this.applyChanges();
    }

    changeRange(start: moment.Moment, end: moment.Moment) {
        this.viewName = 'timelineRange';
        this.start = start;
        this.end = end;
        this.filterByDateRange = {start: this.start, end: this.end};
        LocalStorage.getStorage().set(`filterByDateRangeIN${ window.location.pathname }`, this.filterByDateRange);
        this.applyChanges();
    }

    changeTimezone(timezone: string) {
        this.timezone = timezone;
        localStorage.setItem('statistics-timezone', timezone);
        this.applyChanges();
    }
}
