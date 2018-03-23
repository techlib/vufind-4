/*global Hunt, VuFind */
/*exported checkItemStatuses, itemStatusFail */

function linkCallnumbers(callnumber, callnumber_handler) {
  if (callnumber_handler) {
    var cns = callnumber.split(',\t');
    for (var i = 0; i < cns.length; i++) {
      cns[i] = '<a href="' + VuFind.path + '/Alphabrowse/Home?source=' + encodeURI(callnumber_handler) + '&amp;from=' + encodeURI(cns[i]) + '">' + cns[i] + '</a>';
    }
    return cns.join(',\t');
  }
  return callnumber;
}
function displayItemStatus(result, $item) {
  $item.removeClass('js-item-pending');
  // NTK item status
  if (result.status == 'requested' || result.status == 'Requested'
      || result.status == 'Pravděp. ztráta' || result.status == 'Claimed missing'
      || result.status == 'Ve zpracování' || result.status == 'In process'
      || result.status == 'Objednáno' || result.status == 'On Order'){
      $item.find('.status').empty().append("<span class='label label-danger'>"+result.status+"</span>");
  }else{
      $item.find('.status').empty().append(result.availability_message);
  }
  $item.find('.ajax-availability').removeClass('ajax-availability hidden');
  if (typeof(result.full_status) != 'undefined'
    && result.full_status.length > 0
    && $item.find('.callnumAndLocation').length > 0
  ) {
    // Full status mode is on -- display the HTML and hide extraneous junk:
    $item.find('.callnumAndLocation').empty().append(result.full_status);
    $item.find('.callnumber,.hideIfDetailed,.location,.status').addClass('hidden');
  } else if (typeof(result.missing_data) != 'undefined'
    && result.missing_data
  ) {
    // No data is available -- hide the entire status area:
    $item.find('.callnumAndLocation,.status').addClass('hidden');
  } else if (result.locationList) {
    // We have multiple locations -- build appropriate HTML and hide unwanted labels:
    $item.find('.callnumber,.hideIfDetailed,.location').addClass('hidden');
    var locationListHTML = "";
    for (var x = 0; x < result.locationList.length; x++) {
      locationListHTML += '<div class="groupLocation">';
      if (result.locationList[x].availability) {
        locationListHTML += '<span class="text-success"><i class="fa fa-ok" aria-hidden="true"></i> '
          + result.locationList[x].location + '</span> ';
      } else if (typeof(result.locationList[x].status_unknown) !== 'undefined'
          && result.locationList[x].status_unknown
      ) {
        if (result.locationList[x].location) {
          locationListHTML += '<span class="text-warning"><i class="fa fa-status-unknown" aria-hidden="true"></i> '
            + result.locationList[x].location + '</span> ';
        }
      } else {
        locationListHTML += '<span class="text-danger"><i class="fa fa-remove" aria-hidden="true"></i> '
          + result.locationList[x].location + '</span> ';
      }
      locationListHTML += '</div>';
      locationListHTML += '<div class="groupCallnumber">';
      locationListHTML += (result.locationList[x].callnumbers)
           ? linkCallnumbers(result.locationList[x].callnumbers, result.locationList[x].callnumber_handler) : '';
      locationListHTML += '</div>';
    }
    $item.find('.locationDetails').removeClass('hidden');
    $item.find('.locationDetails').html(locationListHTML);
  } else {
    // Default case -- load call number and location into appropriate containers:
    $item.find('.callnumber').empty().append(linkCallnumbers(result.callnumber, result.callnumber_handler) + '<br/>');
    $item.find('.location').empty().append(
      result.reserve === 'true'
        ? result.reserve_message
        : result.location
    );
    // NTK links
    if (result.location == "V\u0160CHT \u00fastavy"){
        $item.find('.location').unwrap();
        $item.find('.location').empty().append("<a href='https://www.chemtk.cz/cs/82950-seznam-ustavnich-knihoven'>"+result.location+"</a>");
    } else if (result.location == "UCT departments"){
        $item.find('.location').unwrap();
        $item.find('.location').empty().append("<a href='https://www.chemtk.cz/en/82974-departmental-libraries'>"+result.location+"</a>");
    } else if (result.location.indexOf("3D") > 0){ // studovna casopisu
        $item.find('.location').empty().append(result.location);
        $item.find('#linkhref').attr('data-lightbox-href', '../periodicals.php');
    } else if (
            (result.location == "Unknown") || (result.location == "Neznámo") ||
            (result.location == "Sklad historického fondu") || (result.location == "Stack room of historical collection") ||
            (result.location == "Trezor historického fondu") || (result.location == "Reading room of historical collection") ||
            (result.location == "Badatelna historick&eacute;ho fondu") || (result.location == "Safe of historical collection") ||
            (result.location == "Depozitář") || (result.location == "Depository") ||
            (result.location == "Konzultační koutek, 2. NP") || (result.location == "Knowledge Navigation Corner, 2nd floor") ||
            (result.location == "Více umístění") || (result.location == "Multiple Locations") ||
            (result.location == "Sklad") || (result.location == "Stack room") ||
            (result.location == "Volný výběr, nezařazeno") || (result.location == "Open stacks, uncategorized") ||
            (result.location == "ÚOCHB ústav") || (result.location == "IOCB department") ||
            (result.location == "Book news, 4th floor") || (result.location == "Novinky, 4. NP")
            ){
            $item.find('.location').unwrap();
            $item.find('.location').empty().append(result.location);
    } else {
        $item.find('.location').empty().append(result.location);
        $item.find('#linkhref').attr('data-lightbox-href', '../map.php?lcc=map' + result.callnumber);
            var title = result.location;
            if(typeof title === "undefined") {
                title = $(this).html();
            }
            var p,s,r,vysledek,title_desc;
            if (title.indexOf('Shelf') >= 0){
              p = 'floor';
              s = 'section';
              r = 'shelf';
            }else{
              p = 'patro';
              s = 'sekce';
              r = 'regál';
            }
            var patro = title.charAt(6);
            title_desc = p+': '+patro;
            var sekce = title.charAt(7);
            title_desc += ', '+s+': '+sekce;
            var regal = title.substr(8,3);
            title_desc += ', '+r+': '+regal;
            vysledek = title+' ('+title_desc+')';
        $item.find('#linkhref').attr('data-lightbox-title', vysledek);
    }
  }
}
function itemStatusFail(response, textStatus) {
  if (textStatus === 'abort' || typeof response.responseJSON === 'undefined') {
    return;
  }
  // display the error message on each of the ajax status place holder
  $('.js-item-pending').addClass('text-danger').append(response.responseJSON.data);
}

var itemStatusIds = [];
var itemStatusEls = {};
var itemStatusTimer = null;
var itemStatusDelay = 200;
var itemStatusRunning = false;

function runItemAjaxForQueue() {
  // Only run one item status AJAX request at a time:
  if (itemStatusRunning) {
    itemStatusTimer = setTimeout(runItemAjaxForQueue, itemStatusDelay);
    return;
  }
  itemStatusRunning = true;
  $.ajax({
    dataType: 'json',
    method: 'POST',
    url: VuFind.path + '/AJAX/JSON?method=getItemStatuses',
    data: { 'id': itemStatusIds }
  })
  .done(function checkItemStatusDone(response) {
    for (var j = 0; j < response.data.length; j++) {
      displayItemStatus(response.data[j], itemStatusEls[response.data[j].id]);
      itemStatusIds.splice(itemStatusIds.indexOf(response.data[j].id), 1);
    }
    itemStatusRunning = false;
  })
  .fail(function checkItemStatusFail(response, textStatus) {
    itemStatusFail(response, textStatus);
    itemStatusRunning = false;
  });
}

function itemQueueAjax(id, el) {
  if (el.hasClass('js-item-pending')) {
    return;
  }
  clearTimeout(itemStatusTimer);
  itemStatusIds.push(id);
  itemStatusEls[id] = el;
  itemStatusTimer = setTimeout(runItemAjaxForQueue, itemStatusDelay);
  el.addClass('js-item-pending').removeClass('hidden');
  el.find('.status').removeClass('hidden');
}

function checkItemStatus(el) {
  var $item = $(el);
  if ($item.find('.hiddenId').length === 0) {
    return false;
  }
  var id = $item.find('.hiddenId').val();
  itemQueueAjax(id + '', $item);
}

function checkItemStatuses(_container) {
  var container = _container instanceof Element
    ? _container
    : document.body;

  var ajaxItems = $(container).find('.ajaxItem');
  for (var i = 0; i < ajaxItems.length; i++) {
    var id = $(ajaxItems[i]).find('.hiddenId').val();
    itemQueueAjax(id, $(ajaxItems[i]));
  }
  // Stop looking for a scroll loader
  if (itemStatusObserver) {
    itemStatusObserver.disconnect();
  }
}
var itemStatusObserver = null;
$(document).ready(function checkItemStatusReady() {
  if (typeof Hunt === 'undefined') {
    checkItemStatuses();
  } else {
    itemStatusObserver = new Hunt(
      $('.ajaxItem').toArray(),
      { enter: checkItemStatus }
    );
  }
});
