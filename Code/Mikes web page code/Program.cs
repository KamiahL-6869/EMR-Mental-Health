using System.Text.Json;

var builder = WebApplication.CreateBuilder(args);

// Add services to the container.
builder.Services.AddRazorPages();

var app = builder.Build();

// Configure the HTTP request pipeline.
if (!app.Environment.IsDevelopment())
{
    app.UseExceptionHandler("/Error");
    // The default HSTS value is 30 days. You may want to change this for production scenarios, see https://aka.ms/aspnetcore-hsts.
    app.UseHsts();
}

app.UseHttpsRedirection();

app.UseRouting();

app.UseAuthorization();

// Enable serving static files from wwwroot
app.UseStaticFiles();

// Serve the HTML page from the web root (wwwroot/HTMLPage1.html)
app.MapGet("/", () => Results.File(Path.Combine(app.Environment.WebRootPath, "HTMLPage1.html"), "text/html"));

// Simple in-memory store for Communications. Replace with persistent store and auth checks.
var communications = new List<JsonElement>();

app.MapGet("/api/patients/{pid}/communications", (string pid) => Results.Ok(communications));

app.MapPost("/api/patients/{pid}/communications", async (string pid, HttpRequest req) =>
{
    try
    {
        using var doc = await JsonDocument.ParseAsync(req.Body);
        var el = doc.RootElement.Clone();
        communications.Add(el);
        var id = el.TryGetProperty("id", out var idp) ? idp.GetString() ?? Guid.NewGuid().ToString() : Guid.NewGuid().ToString();
        return Results.Created($"/api/patients/{pid}/communications/{id}", el);
    }
    catch
    {
        return Results.BadRequest();
    }
});

app.MapStaticAssets();
app.MapRazorPages()
   .WithStaticAssets();

await app.StartAsync();
var url = "https://localhost:5001"; // use your app URL or read from config
Process.Start(new ProcessStartInfo { FileName = url, UseShellExecute = true });
await app.WaitForShutdownAsync();
