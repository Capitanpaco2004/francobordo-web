//Copyright (c) 2009  Yuriy Peskov
//e-mail: ypeskov@pisem.net
//Released under the GNU General Public License
//version 1.0

//Different variables
function ClientSearch()
{
    this.WRONG_VALUE = 'Неверное значение: ';
    this.WRONG_DIAPOSON = 'Неверный диапозон (значения могут быть ';
}
var Settings = new ClientSearch();

//======================================
//Object DateLimits
//======================================
function DateLimits()
{
    this.FromDay = false;
    this.FromMonth = false;
    this.FromYear = false;
    this.ToDay = false;
    this.ToMonth = false;
    this.ToYear = false;
}
//--------------------------------------
DateLimits.prototype.sayNotCorrectDate = function(Value)
{
    alert(Settings.WRONG_VALUE + Value);
}
//--------------------------------------
DateLimits.prototype.sayNotCorrectDiaposon = function(Value, Diap)
{
    alert(Settings.WRONG_DIAPOSON + Diap + Value);
}
//--------------------------------------
DateLimits.prototype.setDayFlag = function(Field, Flag)
{
    if (Field == '#from_day')
    {
	this.FromDay = Flag;
    }
    else if (Field == '#to_day')
    {
	this.ToDay = Flag;
    }
}
//--------------------------------------
DateLimits.prototype.setMonthFlag = function(Field, Flag)
{
    if (Field == '#from_month')
    {
	this.FromMonth = Flag;
    }
    else if (Field == '#to_month')
    {
	this.ToMonth = Flag;
    }
}
//--------------------------------------
DateLimits.prototype.setYearFlag = function(Field, Flag)
{
    if (Field == '#from_year')
    {
	this.FromYear = Flag;
    }
    else if (Field == '#to_year')
    {
	this.ToYear = Flag;
    }
}
//--------------------------------------
DateLimits.prototype.checkDay = function(FromDay)
{
    var TmpValue = $(FromDay).val();
    var StartDay = parseInt(TmpValue, 10);

    if (isNaN(StartDay))
    {
	this.sayNotCorrectDate(TmpValue);
	$(FromDay).val('');
	this.setDayFlag(FromDay, false);
    }
    else
    {
	if (StartDay < 1 || StartDay > 31)
	{
	    this.sayNotCorrectDiaposon(TmpValue, 'от 1 до 31): ');
	    $(FromDay).val('');
	    this.setDayFlag(FromDay, false);
	}
	else
	{
	    this.setDayFlag(FromDay, true);
	}

    }
}
//--------------------------------------
DateLimits.prototype.checkMonth = function(FromMonth)
{
    var TmpValue = $(FromMonth).val();
    var StartDay = parseInt(TmpValue, 10);

    if (isNaN(StartDay))
    {
	this.sayNotCorrectDate(TmpValue);
	$(FromMonth).val('');
	this.setMonthFlag(FromMonth, false);
    }
    else
    {
	if (StartDay < 1 || StartDay > 12)
	{
	    this.sayNotCorrectDiaposon(TmpValue, 'от 1 до 12): ');
	    $(FromMonth).val('');
	    this.setMonthFlag(FromMonth, false);
	}
	else
	{
	    this.setMonthFlag(FromMonth, true);
	}

    }
}
//--------------------------------------
DateLimits.prototype.checkYear = function(FromYear)
{
    var TmpValue = $(FromYear).val();
    var StartDay = parseInt(TmpValue, 10);

    if (isNaN(StartDay))
    {
	this.sayNotCorrectDate(TmpValue);
	$(FromYear).val('');
	this.setYearFlag(FromYear, false);
    }
    else
    {
	if (StartDay < 2000 || StartDay > 2050)
	{
	    this.sayNotCorrectDiaposon(TmpValue, 'от 2000 до 2050): ');
	    $(FromYear).val('');
	    this.setYearFlag(FromYear, false);
	}
	else
	{
	    this.setYearFlag(FromYear, true);
	}

    }
}
//--------------------------------------
DateLimits.prototype.checkFilter = function()
{
    
    if ($('#filter').attr('checked') == true)
	return true
    else
	return false
}
//--------------------------------------
DateLimits.prototype.checkFields = function()
{
    var ReturnValue = false;
    
    if (this.checkFilter() == true)
    {
	if (this.FromDay == true && this.FromMonth == true && this.FromYear == true &&
	    this.ToDay == true && this.ToMonth == true && this.ToYear == true)
	{
	    ReturnValue = true;
	}
	else
	{
	    alert('Не введены все даты!');
	    ReturnValue = false;
	}
    }
    else
    {
	ReturnValue = true;
    }

    return ReturnValue;
}
//==End of DateLimits===================

var CurLim = new DateLimits();

$(document).ready(function(){
    $('#from_day').blur(function()
    {
	if ($('#from_day').val().length > 0) CurLim.checkDay('#from_day');
    });
    //--------------------------------
    $('#from_month').blur(function()
    {
	if ($('#from_month').val().length > 0) CurLim.checkMonth('#from_month');
    });
    //--------------------------------
    $('#from_year').blur(function()
    {
	if ($('#from_year').val().length > 0) CurLim.checkYear('#from_year');
    });
    //--------------------------------
    $('#to_day').blur(function()
    {
	if ($('#to_day').val().length > 0) CurLim.checkDay('#to_day');
    });
    //--------------------------------
    $('#to_month').blur(function()
    {
	if ($('#to_month').val().length > 0) CurLim.checkMonth('#to_month')
    });
    //--------------------------------
    $('#to_year').blur(function()
    {
	if ($('#to_year').val().length > 0) CurLim.checkYear('#to_year');
    });
    //--------------------------------
    $('#find_client').submit(function()
    {
	return CurLim.checkFields();
    });
});