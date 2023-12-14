let currentPageName = (window.location.href).split('/').pop().split('?')[0];
let all_checkboxes_val = [];
let current_query = null;
let current_total_record = 0;
$(".custom-select-picker").select2();
const fp = flatpickr(".flatpickr_date", {
  mode: "range",
  enableTime: true,
  dateFormat: "Y-m-d H:i",
  minDate: "",
  maxDate: "",
  defaultDate: ["", ""],
  onChange: function (selectedDates, dateStr, instance) {
    if (selectedDates.length === 1) {
      // Set the time for the first date to 12:00 am (00:00)
      const firstDate = selectedDates[0];
      firstDate.setHours(0);
      firstDate.setMinutes(0);
      instance.setDate([firstDate, ""]);
    }
    if (selectedDates.length === 2) {
      // Set the time for the second date to 11:59 pm (23:59)
      const secondDate = selectedDates[1];
      secondDate.setHours(23);
      secondDate.setMinutes(59);
      instance.setDate([selectedDates[0], secondDate]);
    }
  }
});
$("#checkAll").click(function (e) {
  e.stopPropagation();
  $("input.checkboxes").not(this).prop("checked", this.checked);
  get_all_checked_ids();
});

$(document).on('click','input.checkboxes', function(){
  if(this.checked){
    all_checkboxes_val.push(this.value);
  }else{
    let indexToRemove  = all_checkboxes_val.indexOf(this.value);
    all_checkboxes_val.splice(indexToRemove, 1);
  }
});

async function get_all_checked_ids(){
  if($("#checkAll").is(':checked')){
    // console.log(current_query);
    await $.ajax({
      url:'fun/get_checkAll',
      type:'get',
      data:{currentPageName, current_query},
      dataType: 'json',
      success:function(res){
        all_checkboxes_val = res;
      }
    });
    // console.log(all_checkboxes_val);
  }else{
    all_checkboxes_val = [];
  }
}
function handle_checkboxes(){
  if(all_checkboxes_val.length>0){
    $('input.checkboxes').each((index, element)=>{
      if(all_checkboxes_val.includes(element.value)){
        element.checked = true;
      }else{
        element.checked = false;
      }
    });
  }
}

var oldExportAction = function (self, e, dt, button, config) {

  // let self = this;
  // console.log(button);

  if (button[0].className.indexOf('buttons-csv') >= 0) {
      if ($.fn.dataTable.ext.buttons.csvHtml5.available(dt, config)) {
          $.fn.dataTable.ext.buttons.csvHtml5.action.call(self, e, dt, button, config);
      }else {
          $.fn.dataTable.ext.buttons.csvFlash.action.call(self, e, dt, button, config);
      }
  } else if (button[0].className.indexOf('buttons-excel') >= 0) {
    if ($.fn.dataTable.ext.buttons.excelHtml5.available(dt, config)) {
      $.fn.dataTable.ext.buttons.excelHtml5.action.call(self, e, dt, button, config);
    }else {
        $.fn.dataTable.ext.buttons.excelFlash.action.call(self, e, dt, button, config);
    }
  }
};

var newExportAction = function (e, dt, button, config) {

  let old_per_page = dt.page.len();
  let self = this
  var currentSearch = dt.search();

  // Define a draw callback function
  var firstDrawCallback = function () {
    // Remove the first draw callback
    dt.off('draw', firstDrawCallback);

    oldExportAction(self, e, dt, button, config);

    dt.page.len(old_per_page).draw();
    dt.search(currentSearch).draw();
  };

  dt.page.len(current_total_record).draw();
  dt.search(currentSearch).draw();

  // Attach the first draw callback
  dt.on('draw', firstDrawCallback);

};


function loadTable(table_id = "#basic-datatable", url = "", columns=[], default_index = [0, "desc"], where = null, extra_where = null) {
  let table_instance= null;
  // console.log('loadTable');
  // console.log(table_id, columns, where);
  if (table_instance !== null) {
    table_instance.destroy();
  }

  $.fn.dataTable.ext.errMode = 'throw';
  const options = {
    destroy: true,
    autoWidth:false,
    deferRender: true,
    processing: true,
    scrollX: true,
    serverSide: true,
    stateSave: true,
    ajax: {
      url: url,
      type: "GET",
      data: function(data) {
        data.where_condition = where,
        data.extra_where = extra_where
      }
    },
    columns: columns,
    order: [default_index],
    search: {
      regex: true,
      return: true
    },
    // buttons: [
      //   'copy', 'csv', 'excel', 'pdf', 
      //   {extend:'copyHtml5', text:'Copy HTML'}, {extend:'csvHtml5', text:'CSV HTML'}, {extend:'excelHtml5', text:'Excel HTML'}, {extend:'pdfHtml5', text:'PDF HTML'}, 
      //   'print', 
      //   'colvis'],
    dom: '<"top"lBf>rt<"bottom"ip>',
    buttons: [
      {
        extend: 'colvis',
        columns: 'th:nth-child(n)'
      },{
        extend:'csvHtml5', 
        text:'Download CSV',
        exportOptions: {
          columns: ':visible'
        },
        action: newExportAction
      },{
        extend:'excelHtml5', 
        text:'Download Excel',
        exportOptions: {
          columns: ':visible'
        },
        action: newExportAction
      }
    ],
    colReorder: true,
    rowReorder: false,
    fixedHeader: {
      header: true,
      headerOffset: $('.navbar').outerHeight()-6
    },
    keys: true,
    responsive: false,
    // responsive: {
    //   breakpoints: [
    //     // { name: 'xl', width: Infinity },
    //     // { name: 'lg', width: 1200 },
    //     // { name: 'md', width: 992 },
    //     // { name: 'sm', width: 768 },
    //     { name: 'xs', width: 576 }
    //   ],
    //   details: false
    // },
    // select: {
    //   style: 'multi',
    //   items: 'cell'
    // },
    // initComplete: function(settings, json) {
    //   console.log( 'DataTables has finished its initialisation.' );
    // }
    drawCallback: async function (settings) {
      current_query = settings.json.query;
      current_total_record = settings.json.recordsFiltered;
      await get_all_checked_ids();
      handle_checkboxes();
    }
  }
  table_instance = $(`${table_id}`).on('preXhr.dt', function (e, settings, data) {
    console.log("🚀 ~ file: custom.js:211 ~ data:", data)
  }).DataTable(options);
  // console.log(table_instance);
}

function search_select_data(search_field, select_id) {
  let searchVal = $(`${search_field}`).val();
  let search_len = searchVal.length;
  if (search_len >= 2) {
    if ($(`#select2-${select_id}-results`).length > 0) {
      debounceCheck({
        select_id: select_id,
        select_results_id: search_field,
        search_val: searchVal,
      });
    }
  }
}

// calling debounce
const debounceCheck = debounce((obj) => {
  let select_id = obj.select_id;
  let select_results_id = obj.select_results_id;
  let search_val = obj.search_val;

  $.ajax({
    url: "fun/search_select",
    type: "GET",
    data: {
      [select_id]: search_val,
    },
    cache: false,
    success: function (result) {
      result = "<option></option>" + result;
      let selectElem = $(`#${select_id}`);
      selectElem.html(result);
      selectElem.select2("destroy");
      selectElem.select2();
      selectElem.select2("open");
      // $(`#${select_results_id}`).parent().prev().find('.select2-search__field').val(search_val)
      $(`${select_results_id}`).val(search_val);
    },
    error: function (res) {
      //   alert(res + "sertgbn");
    },
  });
}, 250);

//  debounce function
function debounce(cb, delay = 1000) {
  let timeout;
  return (...args) => {
    clearTimeout(timeout);
    timeout = setTimeout(() => {
      cb(...args);
    }, delay);
  };
}



// ##############################################

let form_fields = $("#filter_block").find("input, select");
let setArray = [];

function clear_filter(element) {
  let val = $(element).val();
  
  if(Array.isArray(val) && val.length!=0 && val[0]!=''){
    $(element).val([]);
  }else{

    let nodeName = $(element).prop("nodeName");
    let name = $(element).attr("name");
    // console.log(nodeName, name);
    if (nodeName == "SELECT") {
      $(`[name='${name}'] option`)
        .first()
        .prop("selected", true)
        .trigger("change");
    } else if (nodeName == "INPUT") {
      $(`[name='${name}']`).val("");
    } else {
    }
    
  }
  $("#search_filter").click();
}

$("#clear_filter").click(function () {
  form_fields.each(function () {
    clear_filter(this);
  });
});

$(document).on("click",".remove_filter",function(){
  let filter_selector = $(this).parent().attr('data-filter_selector');
  clear_filter($(`${filter_selector}`));
  $(this).parent().remove();
});

function search_filter(element){

  if(
    $(element).attr('data-isLGOperator')=='true' ||
    $(element).attr('data-isLikeSOperator')=='true' ||
    $(element).attr('data-isLikeEOperator')=='true'
  ){
    return false;
  }

  let id = $(element).attr('id');
  let label_text = $(`label[for=${id}]`).text();

  let val = ($(element).val()).trim();
  let name = $(element).attr('name');
  let isDate = $(element).attr('data-isDate');
  let comma_separated = $(element).attr('data-comma_separated');
  let isLG = $(element).attr('data-isLG');
  let isLike = $(element).attr('data-isLike');
  let isBetween = $(element).attr('data-isBetween');
  let isInterval = $(element).attr('data-isInterval');

  let label_value = '';
  let nodeName = $(element).prop("nodeName");
  if(nodeName=='SELECT'){
    label_value = $(element).children("option").filter(":selected").text();
  }else{
    label_value = val;
  }

  let field = '';
  if(Array.isArray(val) && val.length!=0 && val[0]!=''){
    if(comma_separated=='true'){
      val = val.join(",");
      field = `CONCAT(',', ${name}, ',') LIKE '%,${val},%'`;

    }else {
      val = "'"+val.join("','")+"'";
      field = `${name} IN(${val})`;

    }
  }else if(val!=''){
    if(comma_separated=='true'){
      field = `CONCAT(',', ${name}, ',') LIKE '%,${val},%'`;

    }else if (isDate == 'true') {
      let date = val.split("to");
      field = `${name} between '${date[0].trim()}' and '${date[1].trim()}'`;

    }else if(isLG=='true'){
      let operator = $(`[name=${name}_operator]`).val();
      label_value = `${operator} ${label_value}`
      field = `${name}${operator}'${val}'`;

    }else if(isLike=='true'){
      let s_operator = $(`[name=${name}_s_operator]`).val();
      let e_operator = $(`[name=${name}_e_operator]`).val();
      label_value = `${s_operator}${label_value}${e_operator}`
      field = `${name} LIKE '${s_operator}${val}${e_operator}'`;

    }else if(isInterval=='true'){
      field = `${name}>now()-INTERVAL ${val}`;

    }else if(isBetween=='true'){
      let b_col = name.split("_to_");
      field = `'${val}' between '${b_col[0]}' and '${b_col[1]}'`;

    } else{
      field = `${name}='${val}'`;

    }
  }
    
  if(field!=''){
    setArray.push(field);
    $("#filter_options").append(`<button type="button" data-filter_selector="[name=${name}]" class="btn btn-filter text-white px-2 py-1 d-flex align-items-center justify-content-center"><span>${label_text} : ${label_value}</span><i class="uil uil-times-circle ml-2 remove_filter"></i></button>`);
  }
}











// $(document).on('keyup', ".select2-search__field", function() {
//     let input_ele;
//     for (const item of ["employees","managers"]) {
//         input_ele = $(`[aria-controls="select2-${item}-results"]`);
//         if (input_ele.length > 0) {
//             search_select_data(`[aria-controls="select2-${item}-results"]`, item);
//         }
//     }
// });