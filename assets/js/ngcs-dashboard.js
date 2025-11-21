/**
 * NGCS Excel Messaging Engine – Dashboard JS
 * File: ngcs-dashboard.js
 * Author: NGCS
 */

console.log("NGCS Dashboard JS Loaded");

// Global variables
window.currentBatchID = null;
window.currentBusinessID = null;

let ngcsTableRows = [];
let ngcsCurrentPage = 1;
let ngcsRowsPerPage = 25;


/* ============================================================
   1. LOAD BATCH LIST
   ============================================================ */

function ngcsLoadBatchList(business_id) {
    window.currentBusinessID = business_id;

    $.get(
        NGCS_AJAX.rest_url + "ngcs/v1/batches?business_id=" + business_id,
        function (res) {
            if (!res.success) return;

            let html = `
                <h3 style="margin-bottom:10px;">📦 Excel Uploads</h3>
                <table class="ngcs-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Uploaded</th>
                            <th>Total</th>
                            <th>Sent</th>
                            <th>Failed</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            res.batches.forEach(b => {
                html += `
                <tr>
                    <td>
                        <input type="text" value="${b.name ?? ''}"
                            data-batchid="${b.id}"
                            class="ngcs-batch-name-input">
                    </td>
                    <td>${b.created_at}</td>
                    <td>${b.total_rows}</td>
                    <td>${b.success_rows}</td>
                    <td>${b.failed_rows}</td>
                    <td>
                        <button class="ngcs-btn-primary ngcs-open-batch"
                                data-batchid="${b.id}">
                            Open
                        </button>
                        <button class="ngcs-btn-danger ngcs-del-batch"
                                data-batchid="${b.id}">
                            Delete
                        </button>
                    </td>
                </tr>
                `;
            });

            html += `</tbody></table>`;
            $("#ngcs-batch-list").html(html);
        }
    );
}


/* ============================================================
   2. OPEN BATCH → LOAD TABLE + STATS
   ============================================================ */

$(document).on("click", ".ngcs-open-batch", function () {
    const id = $(this).data("batchid");

    window.currentBatchID = id;

    ngcsLoadBatchStats(id);
    ngcsLoadBatchRows(id);
});


/* ============================================================
   3. LOAD ROWS FOR A BATCH
   ============================================================ */

function ngcsLoadBatchRows(batch_id) {
    $.get(
        NGCS_AJAX.rest_url + "ngcs/v1/get-rows?batch_id=" + batch_id,
        function (res) {
            if (!res.success) return;

            ngcsTableRows = res.rows;
            ngcsCurrentPage = 1;
            ngcsRenderTable();
        }
    );
}


/* ============================================================
   4. RENDER TABLE (Airtable style)
   ============================================================ */

function ngcsRenderTable() {
    const tbody = document.getElementById("ngcs-table-body");
    const thead = document.getElementById("ngcs-table-head");

    tbody.innerHTML = "";
    thead.innerHTML = "";

    if (!ngcsTableRows.length) return;

    const columns = Object.keys(ngcsTableRows[0].data);

    // Header
    let headHTML = "<tr>";
    headHTML += "<th><input type='checkbox' id='ngcs-check-master'></th>";
    columns.forEach(c => {
        headHTML += `<th>${c}</th>`;
    });
    headHTML += "<th>Status</th>";
    headHTML += "<th>Actions</th>";
    headHTML += "</tr>";

    thead.innerHTML = headHTML;

    // Rows – current page
    const start = (ngcsCurrentPage - 1) * ngcsRowsPerPage;
    const end = start + ngcsRowsPerPage;
    const pageRows = ngcsTableRows.slice(start, end);

    pageRows.forEach(row => {
        let rowHTML = `<tr data-rowid="${row.row_id}">`;

        rowHTML += `
            <td><input type="checkbox" class="ngcs-row-checkbox"
                data-rowid="${row.row_id}"></td>
        `;

        Object.values(row.data).forEach(val => {
            rowHTML += `<td>${val ?? ""}</td>`;
        });

        rowHTML += `<td class="ngcs-status-cell">${row.status}</td>`;

        rowHTML += `
            <td>
                <button class="ngcs-btn-danger ngcs-delete-row"
                    data-rowid="${row.row_id}">
                    Delete
                </button>
            </td>
        `;

        rowHTML += "</tr>";

        tbody.innerHTML += rowHTML;
    });

    ngcsRenderPagination();
}


/* ============================================================
   5. PAGINATION
   ============================================================ */

function ngcsRenderPagination() {
    const totalPages = Math.ceil(ngcsTableRows.length / ngcsRowsPerPage);
    const pagDiv = document.getElementById("ngcs-pagination");
    pagDiv.innerHTML = "";

    for (let i = 1; i <= totalPages; i++) {
        let btn = document.createElement("button");
        btn.innerText = i;
        if (i === ngcsCurrentPage) btn.classList.add("active");
        btn.onclick = () => {
            ngcsCurrentPage = i;
            ngcsRenderTable();
        };
        pagDiv.appendChild(btn);
    }
}


/* ============================================================
   6. SELECT ALL / REMOVE ALL
   ============================================================ */

$("#ngcs-select-all").click(() => {
    $(".ngcs-row-checkbox").prop("checked", true);
});

$("#ngcs-remove-all").click(() => {
    $(".ngcs-row-checkbox").prop("checked", false);
});


/* ============================================================
   7. DELETE ROW
   ============================================================ */

$(document).on("click", ".ngcs-delete-row", function () {
    const id = $(this).data("rowid");

    $.post(
        NGCS_AJAX.rest_url + "ngcs/v1/delete-row",
        { row_id: id },
        function () {
            ngcsTableRows = ngcsTableRows.filter(r => r.row_id != id);
            ngcsRenderTable();
            ngcsLoadBatchStats(window.currentBatchID);
        }
    );
});


/* ============================================================
   8. DELETE SELECTED ROWS
   ============================================================ */

$("#ngcs-delete-selected").click(() => {
    $(".ngcs-row-checkbox:checked").each(function () {
        const id = $(this).data("rowid");

        $.post(NGCS_AJAX.rest_url + "ngcs/v1/delete-row", {
            row_id: id
        });

        ngcsTableRows = ngcsTableRows.filter(r => r.row_id != id);
    });

    ngcsRenderTable();
    ngcsLoadBatchStats(window.currentBatchID);
});


/* ============================================================
   9. DELETE ENTIRE BATCH
   ============================================================ */

$("#ngcs-delete-batch").click(() => {
    $.post(
        NGCS_AJAX.rest_url + "ngcs/v1/delete-batch",
        { batch_id: window.currentBatchID },
        function () {
            ngcsTableRows = [];
            ngcsRenderTable();

            $("#ngcs-stats-bar strong").text("0");

            window.currentBatchID = null;
            ngcsLoadBatchList(window.currentBusinessID);
        }
    );
});


/* ============================================================
   10. SEND SELECTED ROWS → n8n
   ============================================================ */

$("#ngcs-send-selected").click(() => {
    let selected = [];

    $(".ngcs-row-checkbox:checked").each(function () {
        selected.push($(this).data("rowid"));
    });

    $.post(NGCS_AJAX.rest_url + "ngcs/v1/send-selected", {
        batch_id: window.currentBatchID,
        rows: selected
    }, function () {
        ngcsLoadBatchStats(window.currentBatchID);
        ngcsRenderTable();
    });
});


/* ============================================================
   11. RENAME BATCH
   ============================================================ */

$(document).on("change", ".ngcs-batch-name-input", function () {
    const id = $(this).data("batchid");
    const name = $(this).val();

    $.post(NGCS_AJAX.rest_url + "ngcs/v1/rename-batch", {
        batch_id: id,
        name: name
    });
});


/* ============================================================
   12. STATUS POLLING — EVERY 5 SECONDS
   ============================================================ */

setInterval(function () {
    if (window.currentBatchID) {
        ngcsLoadBatchStats(window.currentBatchID);
    }
}, 5000);


/* ============================================================
   13. LOAD BATCH STATS + UPDATE STATUS BAR
   ============================================================ */

function ngcsLoadBatchStats(batch_id) {
    $.get(
        NGCS_AJAX.rest_url + "ngcs/v1/batch-stats?batch_id=" + batch_id,
        function (response) {
            if (!response.success) return;

            const stats = response.stats;

            $("#ngcs-stat-total").text(stats.total);
            $("#ngcs-stat-sent").text(stats.sent);
            $("#ngcs-stat-delivered").text(stats.delivered);
            $("#ngcs-stat-read").text(stats.read);
            $("#ngcs-stat-failed").text(stats.failed);
            $("#ngcs-stat-invalid").text(stats.invalid);
            $("#ngcs-stat-duplicate").text(stats.duplicate);
            $("#ngcs-stat-not-sent").text(stats.not_sent);

            // Selected rows count
            const selected = $(".ngcs-row-checkbox:checked").length;
            $("#ngcs-stat-selected").text(selected);

            // Refresh row statuses
            ngcsRefreshRowStatuses(batch_id);
        }
    );
}


/* ============================================================
   14. REFRESH ROW STATUSES (WITH HIGHLIGHT)
   ============================================================ */

function ngcsRefreshRowStatuses(batch_id) {
    $.get(
        NGCS_AJAX.rest_url + "ngcs/v1/get-rows?batch_id=" + batch_id,
        function (res) {
            if (!res.success) return;

            res.rows.forEach(row => {
                const rowCell = document.querySelector(
                    `tr[data-rowid="${row.row_id}"] td.ngcs-status-cell`
                );

                if (!rowCell) return;

                if (rowCell.innerText !== row.status) {
                    ngcsApplyStatusHighlight(row.row_id, row.status);
                    rowCell.innerText = row.status;
                }
            });
        }
    );
}


/* ============================================================
   15. STATUS HIGHLIGHT ANIMATION
   ============================================================ */

function ngcsApplyStatusHighlight(rowId, status) {
    const row = document.querySelector(`tr[data-rowid="${rowId}"]`);
    if (!row) return;

    row.classList.remove(
        "ngcs-status-flash-sent",
        "ngcs-status-flash-delivered",
        "ngcs-status-flash-read",
        "ngcs-status-flash-failed",
        "ngcs-status-flash-invalid",
        "ngcs-status-flash-duplicate"
    );

    row.classList.add(`ngcs-status-flash-${status}`);

    setTimeout(() => {
        row.classList.remove(`ngcs-status-flash-${status}`);
    }, 2000);
}

