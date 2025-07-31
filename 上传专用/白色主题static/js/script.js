// 模拟从后端获取数据
document.addEventListener("DOMContentLoaded", function() {
    const paymentData = [
        { orderNumber: 'ORD123456', amount: '￥100.00', paymentMethod: '支付宝', paymentTime: '2025-07-05 12:30:00', status: '成功' },
        { orderNumber: 'ORD123457', amount: '￥200.00', paymentMethod: '微信支付', paymentTime: '2025-07-06 08:15:00', status: '失败' },
        { orderNumber: 'ORD123458', amount: '￥150.00', paymentMethod: '银行卡', paymentTime: '2025-07-06 10:00:00', status: '成功' },
        { orderNumber: 'ORD123459', amount: '￥50.00', paymentMethod: '支付宝', paymentTime: '2025-07-06 11:45:00', status: '待处理' }
    ];

    function loadPaymentData(data) {
        const tableBody = document.querySelector("#paymentTable tbody");
        tableBody.innerHTML = ''; // 清空表格
        data.forEach(item => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${item.orderNumber}</td>
                <td>${item.amount}</td>
                <td>${item.paymentMethod}</td>
                <td>${item.paymentTime}</td>
                <td>${item.status}</td>
            `;
            tableBody.appendChild(row);
        });
    }

    // 初始化加载数据
    loadPaymentData(paymentData);

    // 刷新按钮
    document.getElementById('reloadButton').addEventListener('click', function() {
        // 这里可以根据后端接口重新加载数据
        alert('刷新数据');
    });
});
