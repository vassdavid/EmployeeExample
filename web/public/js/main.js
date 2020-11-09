const API_HOST = 'http://localhost:8000';
function paging(page) {
  Site.page = page;
}
/**
 * Szerkesztés feldolgozását végző fv
 * @param  {Object} event Javascript event
 *
 */
function sendEditForm(event) {
  event.preventDefault();
  let formData = $(event.target).serializeArray();
  let employee = {};
  formData.forEach((item) => {
    employee[item.name] = item.value;
  });
  $.post(API_HOST + '?entity=employee&action=modify', employee, function(result){
    Site.modifyEmployee(result);
    $('#editEmployeeModal').modal('hide');
  }).fail(function(){
    alert('Error: item wasn\'t modified');
  });
}
/**
 * Szűrés végző fv: szűréskor a Site object megfelelő paraméterei beállításra kerülnek, a lap újrarendelődik
 * @param  {Object} event
 *
 */
function filterEmployees(event) {
  event.preventDefault();
  let formData = $(event.target).serializeArray();
  let filters = [];
  formData.forEach((input) => {
    if(input.name == 'order_direction') {
      Site.order_direction = input.value;
    } else if(input.name == 'order') {
      Site.order_field = input.value;
    } else if(input.name.indexOf('filter') === 0){
      filters.push(input);
    }
  });
  Site.filters = filters;
  $('#filterModal').modal('hide');
  Site.renderPage();
}
/**
 * Templateket tartalmazó Object a templatek függvények a megadott paraméterek alapján egy sztringet kapunk vissza
 * @type {Object}
 */
let SiteTemplate = {
  /**
   * Listát adó táblázat
   * @param  {array} rows API-ból jövő adatok sorai
   * @return {string}
   */
  list: function(rows) {
    if(!rows || rows.length < 1) {
      return "No result.";
    }
    let render = `<table id="employee-list-table" class="table table-striped">
    <thead>
      <tr>
        <th scope="col">#</th>
        <th scope="col">First name</th>
        <th scope="col">Last name</th>
        <th scope="col">birth date</th>
        <th scope="col">gender</th>
        <th scope="col">hire date</th>
        <th scope="col">Department</th>
        <th scope="col">Title</th>
        <th scope="col">Salary</th>
        <th scope="col">Modify</th>
        <th scope="col">Remove</th>
      </tr>
    </thead><tbody>`;
    rows.forEach((item, i) => {
      render += `<tr data-employee='${JSON.stringify(item)}'>
        <th class="employee-emp_no" scope="row">${item.emp_no}</th>
        <td class="employee-first_name">${item.first_name}</td>
        <td class="employee-last_name">${item.last_name}</td>
        <td class="employee-birth_date">${item.birth_date}</td>
        <td class="employee-gender">${item.gender}</td>
        <td class="employee-hire_date">${item.hire_date}</td>
        <td class="employee-dept_name">${item.dept_name}</td>
        <td class="employee-title">${item.title}</td>
        <td class="employee-salary">${item.salary}</td>
        <td><button type="button" class="btn btn-secondary edit-employee-button" data-toggle="modal" data-target="#editEmployeeModal">Edit</button></td>
        <td><button type="button" class="btn btn-danger remove-employee-button">Remove</button></td>
      </tr>`;
    });
    render += '</tbody></table>';
    return render;
  },
  /**
   * Lapozó
   * @param  {int} page    aktuális oldal
   * @param  {int} maxPage legutolsó oldal
   * @return {string}
   */
  pagination: function(page, maxPage) {
    if(!maxPage && maxPage == 1) {
      return '';
    }
    let render = `<nav aria-label="..."><ul class="pagination justify-content-center">`;
    let fromPage, toPage, pagingNum = 5;
    if(page > pagingNum) {
      fromPage = page - pagingNum;
      toPage = page + pagingNum > maxPage ? maxPage : page + pagingNum;
    } else {
      fromPage = 1;
      toPage = page + pagingNum*2 > maxPage ? maxPage : page + pagingNum*2;
    }
    if(fromPage > 1) {
      render += `<li class="page-item">
        <a class="page-link" onclick="page(1)" href="#">First</a>
      </li>
      <li class="page-item disabled">
        <a class="page-link" href="#">...</a>
      </li>`;
    }
    for (let i = fromPage; i <= toPage; i++) {
      render += `<li class="page-item  ${ i== page ? 'active' : ''}"><a class="page-link"  onclick="paging(${i})" href="#">${i}</a></li>`;
    }
    if(toPage < maxPage) {
      render += `<li class="page-item disabled">
        <a class="page-link" href="#">...</a>
      </li>
      <li class="page-item">
        <a class="page-link" onclick="paging(${maxPage})" href="#">Last</a>
      </li>`;
    }
    render += `</ul></nav>`;
    return render;
  },
}
/**
 * Ez az osztály foglalja össze az oldalon történő változások kezelését
 * @type {Object}
 */
let Site = {
  _action: 'list',
  _page: 1,
  //[name: string, value: string]
  _filters: [],
  _order_field: '',
  _order_direction: 1,
  /**
   * rendezéshez szükséges get querieket szedi össze
   * @return {string} GET query
   */
  get orderQuery() {
    let query = '';
    if(this._order_field.length > 0) {
      query = `&order=${this._order_field}&order_direction=${this._order_direction}`;
    }
    return query;
  },
  /**
   * szűréshez szükséges get querieket szedi össze
   * @return {string} GET query
   */
  get filterQuery() {
      let query = '';
      if(this._filters.length > 0) {
        let queries = [];
        for(let i=0; i<this._filters.length; i++) {
          if(this._filters[i].value.length > 0) {
            queries.push(this._filters[i].name + '=' + this._filters[i].value);
          }
        }
        query = '&' + queries.join('&');
      }
      return query;
  },
  /**
   * lapozás Get query
   * @return {string}
   */
  get pageQuery() {
    return `&page=${this.page}`;
  },
  get action() {
    return this._action;
  },
  get page() {
    return this._page;
  },
  set page(page) {
    if(this._page != page) {
      this._page = page;
      this.renderPage();
    }
  },
  get order_field() {
    return this._order_field;
  },
  get order_direction() {
    return this._order_direction;
  },
  set order_field(field) {
    this._order_field = field;
  },
  set order_direction(direction) {
    this._order_direction = direction;
  },
  set filters(filters) {
    this._filters = filters;
  },
  /**
   * Employee módosítása után frissíti a táblázatot
   * @param  {Object} item egy adott sor adatai (employee)
   *
   */
  modifyEmployee(item) {
    $('#employee-list-table tr').each(function(){
      let employee = $(this).data('employee');
      if(employee && employee.emp_no == item.emp_no) {
        for(const key in item) {
          $(this).children(`td.employee-${key}`).text(item[key]);
        }
        //break the loop
        return false;
      }
    });
  },
  /**
   * A táblázaton kitörli a törölt elmet
   * @param  {int} emp_no
   *
   */
  removeEmployee(emp_no) {
    $('#employee-list-table tr').each(function(){
      let employee = $(this).data('employee');
      if(employee && employee.emp_no == emp_no) {
        $(this).remove();
        //break the loop
        return false;
      }
    });
  },
  //lerendeli az adott listát
  renderPage() {
    //resets
    $('#main-container').html('Loading..');
    $('#main-footer').html('');
    $.get(API_HOST + '?entity=employee&action='+this.action + this.orderQuery + this.filterQuery + this.pageQuery, (result) => {
      let rows = SiteTemplate.list(result.data);
      $('#main-container').html(rows);
      if(this.action == 'list') {
        let pagination = SiteTemplate.pagination(result.page, result.pages);
        $('#main-footer').html(pagination);
      }
      //filterek beállítása a használt paraméterek alapján
      Site.resetFilters();
    });
  },
  /**
   * Filterek beállítása a Site object alapján
   */
  resetFilters() {
    //filter
    this._filters.forEach((item) => {
      $(`#page-filter [name="${item.name}"]`).val(item.value);
    });
    //order
    $(`#page-filter [name="order"]`).val(this._order_field);
    $(`#page-filter [name="order_direction"]`).val(this._order_direction);
  }
};

$(document).ready(function(){
  Site.renderPage();
  //fill edit modal
  $('#main-container').on('click','.edit-employee-button',function() {
    let data = $(this).parent('td').parent('tr').data('employee');
    let inputs = [
      'emp_no',
      'first_name',
      'last_name',
      'birth_date',
      'gender',
      'hire_date'
    ];
    inputs.forEach((input, i) => {
      $(`#editEmployeeModal [name="${input}"]`).val(data[input]);
    });
  });
  //delete emloyee
  $('#main-container').on('click','.remove-employee-button',function(){
      let employee = $(this).parent('td').parent('tr').data('employee');
      $.post(API_HOST + '?entity=employee&action=delete', employee.emp_no, function(result){
        Site.removeEmployee(employee.emp_no);
      }).fail(function(){
        alert("Unable to delete item, error happened");
      });

  });
});
